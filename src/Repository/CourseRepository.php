<?php

namespace App\Repository;

use App\Service\ZoomMeetingProvider;
use DateTimeImmutable;
use DomainException;
use PDO;
use RuntimeException;

final class CourseRepository
{
    private bool $schemaReady = false;

    public function __construct(
        private readonly DatabaseConnection $databaseConnection,
        private readonly ZoomMeetingProvider $zoomMeetingProvider,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $this->ensureSchema();

        $rows = $this->pdo()
            ->query('SELECT * FROM fl_courses ORDER BY course_date ASC, course_time ASC, title ASC')
            ->fetchAll();

        return $this->rowsToCourses($rows);
    }

    /**
     * @param array<string, mixed> $course
     *
     * @return array<string, mixed>
     */
    public function create(array $course): array
    {
        $this->ensureSchema();
        $course = $this->zoomMeetingProvider->getOrCreateMeeting($course);

        $this->insertCourse($course);

        return $course;
    }

    /**
     * @param array<int, array<string, mixed>> $courses
     *
     * @return array<int, array<string, mixed>>
     */
    public function reset(array $courses): array
    {
        $this->ensureSchema();
        $pdo = $this->pdo();
        $pdo->beginTransaction();

        try {
            $pdo->exec('DELETE FROM fl_bookings');
            $pdo->exec('DELETE FROM fl_courses');

            foreach ($courses as $course) {
                $course = $this->zoomMeetingProvider->getOrCreateMeeting($course);
                $this->insertCourse($course);
                foreach ($course['bookings'] ?? [] as $booking) {
                    $this->insertBooking($course['id'], $booking);
                }
            }

            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }

        return $this->all();
    }

    public function delete(string $id): void
    {
        $this->ensureSchema();

        $statement = $this->pdo()->prepare('DELETE FROM fl_courses WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function syncZoom(string $id): ?array
    {
        $this->ensureSchema();
        $course = $this->find($id);
        if ($course === null) {
            return null;
        }

        $course = $this->zoomMeetingProvider->getOrCreateMeeting($course);
        $this->updateZoom($course);

        return $this->find($id);
    }

    /**
     * @param array<string, string> $booking
     *
     * @return array{course: array<string, mixed>, booking: array<string, string>}
     */
    public function addBooking(string $courseId, array $booking): array
    {
        $this->ensureSchema();
        $pdo = $this->pdo();
        $pdo->beginTransaction();

        try {
            $course = $this->find($courseId);
            if ($course === null) {
                throw new DomainException('course-not-found');
            }

            if ($course['booked'] >= $course['capacity']) {
                throw new DomainException('course-full');
            }

            $course = $this->zoomMeetingProvider->getOrCreateMeeting($course);
            $this->updateZoom($course);
            $this->insertBooking($courseId, $booking);

            $booked = min($course['booked'] + 1, $course['capacity']);
            $status = $booked >= $course['capacity'] ? 'ausgebucht' : $course['status'];
            if ($status === 'ausgebucht' && $booked < $course['capacity']) {
                $status = 'offen';
            }

            $statement = $pdo->prepare('UPDATE fl_courses SET booked = :booked, status = :status, updated_at = :updated_at WHERE id = :id');
            $statement->execute([
                'booked' => $booked,
                'status' => $status,
                'updated_at' => $this->now(),
                'id' => $courseId,
            ]);

            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }

        return [
            'course' => $this->find($courseId),
            'booking' => $booking,
        ];
    }

    /**
     * @param array<string, string> $order
     *
     * @return array{course: array<string, mixed>, booking: array<string, string>, alreadySent: bool, created: bool}
     */
    public function addPaidBookingFromWebhook(array $order): array
    {
        $this->ensureSchema();
        $pdo = $this->pdo();
        $pdo->beginTransaction();

        try {
            $provider = $order['provider'] ?? 'tentary';
            $orderId = trim((string) ($order['orderId'] ?? ''));
            $existing = $this->findBookingContextByExternalOrder($provider, $orderId);

            if ($existing !== null) {
                $this->markBookingPaid($existing['booking']['id'], $order['paidAt'] ?? '');
                $context = $this->findBookingContext($existing['booking']['id']);
                if ($context === null) {
                    throw new RuntimeException('Buchung nach Zahlungsabgleich nicht mehr gefunden.');
                }

                $pdo->commit();

                return [
                    'course' => $context['course'],
                    'booking' => $context['booking'],
                    'alreadySent' => ($context['booking']['confirmationSentAt'] ?? '') !== '',
                    'created' => false,
                ];
            }

            $course = $this->findCourseForWebhook($order['courseId'] ?? '', $order['courseTitle'] ?? '');
            if ($course === null) {
                throw new DomainException('course-not-found');
            }

            if ($course['booked'] >= $course['capacity']) {
                throw new DomainException('course-full');
            }

            $course = $this->zoomMeetingProvider->getOrCreateMeeting($course);
            $this->updateZoom($course);

            $paidAt = $order['paidAt'] ?? date('c');
            $booking = [
                'id' => bin2hex(random_bytes(8)),
                'name' => $order['customerName'] ?? 'Kundin/Kunde',
                'email' => $order['customerEmail'] ?? '',
                'note' => $order['note'] ?? '',
                'paymentStatus' => 'paid',
                'paymentConfirmedAt' => $paidAt,
                'confirmationSentAt' => '',
                'createdAt' => date('c'),
                'externalProvider' => $provider,
                'externalOrderId' => $orderId,
                'externalProductId' => $order['productId'] ?? '',
                'externalPaidAt' => $paidAt,
            ];

            $this->insertBooking($course['id'], $booking);

            $booked = min($course['booked'] + 1, $course['capacity']);
            $status = $booked >= $course['capacity'] ? 'ausgebucht' : $course['status'];
            if ($status === 'ausgebucht' && $booked < $course['capacity']) {
                $status = 'offen';
            }

            $statement = $pdo->prepare('UPDATE fl_courses SET booked = :booked, status = :status, updated_at = :updated_at WHERE id = :id');
            $statement->execute([
                'booked' => $booked,
                'status' => $status,
                'updated_at' => $this->now(),
                'id' => $course['id'],
            ]);

            $context = $this->findBookingContext($booking['id']);
            if ($context === null) {
                throw new RuntimeException('Webhook-Buchung wurde nicht mehr gefunden.');
            }

            $pdo->commit();

            return [
                'course' => $context['course'],
                'booking' => $context['booking'],
                'alreadySent' => false,
                'created' => true,
            ];
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    /**
     * @return array{course: array<string, mixed>, booking: array<string, string>, alreadySent: bool}
     */
    public function confirmPayment(string $bookingId): array
    {
        $this->ensureSchema();
        $context = $this->findBookingContext($bookingId);
        if ($context === null) {
            throw new DomainException('booking-not-found');
        }

        $alreadySent = ($context['booking']['confirmationSentAt'] ?? '') !== '';
        $statement = $this->pdo()->prepare(<<<'SQL'
UPDATE fl_bookings
SET payment_status = 'paid',
    payment_confirmed_at = COALESCE(payment_confirmed_at, :payment_confirmed_at)
WHERE id = :id
SQL);
        $statement->execute([
            'payment_confirmed_at' => $this->now(),
            'id' => $bookingId,
        ]);

        $context = $this->findBookingContext($bookingId);
        if ($context === null) {
            throw new RuntimeException('Buchung nach Zahlung nicht mehr gefunden.');
        }

        return [
            'course' => $context['course'],
            'booking' => $context['booking'],
            'alreadySent' => $alreadySent,
        ];
    }

    public function markConfirmationSent(string $bookingId): void
    {
        $this->ensureSchema();

        $statement = $this->pdo()->prepare(<<<'SQL'
UPDATE fl_bookings
SET confirmation_sent_at = COALESCE(confirmation_sent_at, :confirmation_sent_at)
WHERE id = :id
SQL);
        $statement->execute([
            'confirmation_sent_at' => $this->now(),
            'id' => $bookingId,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $id): ?array
    {
        $this->ensureSchema();

        $statement = $this->pdo()->prepare('SELECT * FROM fl_courses WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $this->rowToCourse($row, $this->bookingsForCourse($id)) : null;
    }

    private function ensureSchema(): void
    {
        if ($this->schemaReady) {
            return;
        }

        $pdo = $this->pdo();
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS fl_courses (
    id VARCHAR(32) NOT NULL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    teacher VARCHAR(255) NOT NULL,
    course_date DATE NOT NULL,
    course_time TIME NOT NULL,
    capacity INT NOT NULL,
    booked INT NOT NULL DEFAULT 0,
    status VARCHAR(32) NOT NULL,
    zoom_link TEXT NOT NULL,
    zoom_meeting_id VARCHAR(64) NOT NULL DEFAULT '',
    zoom_password VARCHAR(64) NOT NULL DEFAULT '',
    zoom_provider VARCHAR(64) NOT NULL DEFAULT '',
    zoom_status VARCHAR(64) NOT NULL DEFAULT '',
    zoom_last_synced_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_fl_courses_date (course_date, course_time),
    INDEX idx_fl_courses_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS fl_bookings (
    id VARCHAR(32) NOT NULL PRIMARY KEY,
    course_id VARCHAR(32) NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    note TEXT NULL,
    payment_status VARCHAR(32) NOT NULL DEFAULT 'open',
    payment_confirmed_at DATETIME NULL,
    confirmation_sent_at DATETIME NULL,
    external_provider VARCHAR(64) NOT NULL DEFAULT '',
    external_order_id VARCHAR(128) NOT NULL DEFAULT '',
    external_product_id VARCHAR(128) NOT NULL DEFAULT '',
    external_paid_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_fl_bookings_course_id (course_id),
    INDEX idx_fl_bookings_payment_status (payment_status),
    INDEX idx_fl_bookings_external_order (external_provider, external_order_id),
    CONSTRAINT fk_fl_bookings_course FOREIGN KEY (course_id) REFERENCES fl_courses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->ensureBookingColumn('payment_status', "ALTER TABLE fl_bookings ADD COLUMN payment_status VARCHAR(32) NOT NULL DEFAULT 'open' AFTER note");
        $this->ensureBookingColumn('payment_confirmed_at', 'ALTER TABLE fl_bookings ADD COLUMN payment_confirmed_at DATETIME NULL AFTER payment_status');
        $this->ensureBookingColumn('confirmation_sent_at', 'ALTER TABLE fl_bookings ADD COLUMN confirmation_sent_at DATETIME NULL AFTER payment_confirmed_at');
        $this->ensureBookingColumn('external_provider', "ALTER TABLE fl_bookings ADD COLUMN external_provider VARCHAR(64) NOT NULL DEFAULT '' AFTER confirmation_sent_at");
        $this->ensureBookingColumn('external_order_id', "ALTER TABLE fl_bookings ADD COLUMN external_order_id VARCHAR(128) NOT NULL DEFAULT '' AFTER external_provider");
        $this->ensureBookingColumn('external_product_id', "ALTER TABLE fl_bookings ADD COLUMN external_product_id VARCHAR(128) NOT NULL DEFAULT '' AFTER external_order_id");
        $this->ensureBookingColumn('external_paid_at', 'ALTER TABLE fl_bookings ADD COLUMN external_paid_at DATETIME NULL AFTER external_product_id');
        $this->ensureBookingIndex('idx_fl_bookings_payment_status', 'ALTER TABLE fl_bookings ADD INDEX idx_fl_bookings_payment_status (payment_status)');
        $this->ensureBookingIndex('idx_fl_bookings_external_order', 'ALTER TABLE fl_bookings ADD INDEX idx_fl_bookings_external_order (external_provider, external_order_id)');

        $this->schemaReady = true;
    }

    private function ensureBookingColumn(string $column, string $sql): void
    {
        $statement = $this->pdo()->prepare("SHOW COLUMNS FROM fl_bookings LIKE :column");
        $statement->execute(['column' => $column]);
        if ($statement->fetch() === false) {
            $this->pdo()->exec($sql);
        }
    }

    private function ensureBookingIndex(string $index, string $sql): void
    {
        $statement = $this->pdo()->prepare("SHOW INDEX FROM fl_bookings WHERE Key_name = :index_name");
        $statement->execute(['index_name' => $index]);
        if ($statement->fetch() === false) {
            $this->pdo()->exec($sql);
        }
    }

    /**
     * @param array<string, mixed> $course
     */
    private function insertCourse(array $course): void
    {
        $now = $this->now();
        $statement = $this->pdo()->prepare(<<<'SQL'
INSERT INTO fl_courses (
    id, title, teacher, course_date, course_time, capacity, booked, status,
    zoom_link, zoom_meeting_id, zoom_password, zoom_provider, zoom_status,
    zoom_last_synced_at, created_at, updated_at
) VALUES (
    :id, :title, :teacher, :course_date, :course_time, :capacity, :booked, :status,
    :zoom_link, :zoom_meeting_id, :zoom_password, :zoom_provider, :zoom_status,
    :zoom_last_synced_at, :created_at, :updated_at
)
SQL);

        $statement->execute([
            'id' => $course['id'],
            'title' => $course['title'],
            'teacher' => $course['teacher'],
            'course_date' => $course['date'],
            'course_time' => $course['time'],
            'capacity' => $course['capacity'],
            'booked' => $course['booked'],
            'status' => $course['status'],
            'zoom_link' => $course['zoomLink'],
            'zoom_meeting_id' => $course['zoomMeetingId'] ?? '',
            'zoom_password' => $course['zoomPassword'] ?? '',
            'zoom_provider' => $course['zoomProvider'] ?? '',
            'zoom_status' => $course['zoomStatus'] ?? '',
            'zoom_last_synced_at' => $this->toMysqlDateTime($course['zoomLastSyncedAt'] ?? null),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param array<string, string> $booking
     */
    private function insertBooking(string $courseId, array $booking): void
    {
        $statement = $this->pdo()->prepare(<<<'SQL'
INSERT INTO fl_bookings (
    id, course_id, name, email, note, payment_status,
    payment_confirmed_at, confirmation_sent_at, external_provider,
    external_order_id, external_product_id, external_paid_at, created_at
) VALUES (
    :id, :course_id, :name, :email, :note, :payment_status,
    :payment_confirmed_at, :confirmation_sent_at, :external_provider,
    :external_order_id, :external_product_id, :external_paid_at, :created_at
)
SQL);

        $statement->execute([
            'id' => $booking['id'],
            'course_id' => $courseId,
            'name' => $booking['name'],
            'email' => $booking['email'],
            'note' => $booking['note'] ?? '',
            'payment_status' => $booking['paymentStatus'] ?? 'open',
            'payment_confirmed_at' => $this->toMysqlDateTime($booking['paymentConfirmedAt'] ?? null),
            'confirmation_sent_at' => $this->toMysqlDateTime($booking['confirmationSentAt'] ?? null),
            'external_provider' => $booking['externalProvider'] ?? '',
            'external_order_id' => $booking['externalOrderId'] ?? '',
            'external_product_id' => $booking['externalProductId'] ?? '',
            'external_paid_at' => $this->toMysqlDateTime($booking['externalPaidAt'] ?? null),
            'created_at' => $this->toMysqlDateTime($booking['createdAt'] ?? null) ?? $this->now(),
        ]);
    }

    /**
     * @param array<string, mixed> $course
     */
    private function updateZoom(array $course): void
    {
        $statement = $this->pdo()->prepare(<<<'SQL'
UPDATE fl_courses
SET zoom_link = :zoom_link,
    zoom_meeting_id = :zoom_meeting_id,
    zoom_password = :zoom_password,
    zoom_provider = :zoom_provider,
    zoom_status = :zoom_status,
    zoom_last_synced_at = :zoom_last_synced_at,
    updated_at = :updated_at
WHERE id = :id
SQL);

        $statement->execute([
            'zoom_link' => $course['zoomLink'],
            'zoom_meeting_id' => $course['zoomMeetingId'] ?? '',
            'zoom_password' => $course['zoomPassword'] ?? '',
            'zoom_provider' => $course['zoomProvider'] ?? '',
            'zoom_status' => $course['zoomStatus'] ?? '',
            'zoom_last_synced_at' => $this->toMysqlDateTime($course['zoomLastSyncedAt'] ?? null),
            'updated_at' => $this->now(),
            'id' => $course['id'],
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     *
     * @return array<int, array<string, mixed>>
     */
    private function rowsToCourses(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $bookingsByCourseId = $this->bookingsByCourseIds(array_map(
            static fn (array $row): string => (string) $row['id'],
            $rows
        ));

        return array_map(
            fn (array $row): array => $this->rowToCourse($row, $bookingsByCourseId[(string) $row['id']] ?? []),
            $rows
        );
    }

    /**
     * @param array<string, mixed>              $row
     * @param array<int, array<string, string>> $bookings
     *
     * @return array<string, mixed>
     */
    private function rowToCourse(array $row, array $bookings = []): array
    {
        return [
            'id' => (string) $row['id'],
            'title' => (string) $row['title'],
            'teacher' => (string) $row['teacher'],
            'date' => (string) $row['course_date'],
            'time' => substr((string) $row['course_time'], 0, 5),
            'capacity' => (int) $row['capacity'],
            'booked' => (int) $row['booked'],
            'status' => (string) $row['status'],
            'zoomLink' => (string) $row['zoom_link'],
            'zoomMeetingId' => (string) $row['zoom_meeting_id'],
            'zoomPassword' => (string) $row['zoom_password'],
            'zoomProvider' => (string) $row['zoom_provider'],
            'zoomStatus' => (string) $row['zoom_status'],
            'zoomLastSyncedAt' => (string) ($row['zoom_last_synced_at'] ?? ''),
            'bookings' => $bookings,
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function bookingsForCourse(string $courseId): array
    {
        $statement = $this->pdo()->prepare('SELECT * FROM fl_bookings WHERE course_id = :course_id ORDER BY created_at ASC, name ASC');
        $statement->execute(['course_id' => $courseId]);

        return array_map($this->rowToBooking(...), $statement->fetchAll());
    }

    /**
     * @param array<int, string> $courseIds
     *
     * @return array<string, array<int, array<string, string>>>
     */
    private function bookingsByCourseIds(array $courseIds): array
    {
        $courseIds = array_values(array_unique(array_filter($courseIds)));
        if ($courseIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($courseIds), '?'));
        $statement = $this->pdo()->prepare(sprintf(
            'SELECT * FROM fl_bookings WHERE course_id IN (%s) ORDER BY course_id ASC, created_at ASC, name ASC',
            $placeholders
        ));
        $statement->execute($courseIds);

        $bookings = [];
        foreach ($statement->fetchAll() as $row) {
            $courseId = (string) $row['course_id'];
            $bookings[$courseId][] = $this->rowToBooking($row);
        }

        return $bookings;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, string>
     */
    private function rowToBooking(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'name' => (string) $row['name'],
            'email' => (string) $row['email'],
            'note' => (string) ($row['note'] ?? ''),
            'paymentStatus' => (string) ($row['payment_status'] ?? 'open'),
            'paymentConfirmedAt' => (string) ($row['payment_confirmed_at'] ?? ''),
            'confirmationSentAt' => (string) ($row['confirmation_sent_at'] ?? ''),
            'externalProvider' => (string) ($row['external_provider'] ?? ''),
            'externalOrderId' => (string) ($row['external_order_id'] ?? ''),
            'externalProductId' => (string) ($row['external_product_id'] ?? ''),
            'externalPaidAt' => (string) ($row['external_paid_at'] ?? ''),
            'createdAt' => (string) $row['created_at'],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findCourseForWebhook(string $courseId, string $courseTitle): ?array
    {
        $courseId = trim($courseId);
        if ($courseId !== '') {
            return $this->find($courseId);
        }

        $courseTitle = trim($courseTitle);
        if ($courseTitle === '') {
            return null;
        }

        $statement = $this->pdo()->prepare(<<<'SQL'
SELECT *
FROM fl_courses
WHERE title = :title
ORDER BY course_date ASC, course_time ASC
LIMIT 1
SQL);
        $statement->execute(['title' => $courseTitle]);
        $row = $statement->fetch();

        return is_array($row) ? $this->rowToCourse($row, $this->bookingsForCourse((string) $row['id'])) : null;
    }

    /**
     * @return array{course: array<string, mixed>, booking: array<string, string>}|null
     */
    private function findBookingContextByExternalOrder(string $provider, string $orderId): ?array
    {
        $provider = trim($provider);
        $orderId = trim($orderId);
        if ($provider === '' || $orderId === '') {
            return null;
        }

        $statement = $this->pdo()->prepare(<<<'SQL'
SELECT id
FROM fl_bookings
WHERE external_provider = :provider
  AND external_order_id = :order_id
LIMIT 1
SQL);
        $statement->execute([
            'provider' => $provider,
            'order_id' => $orderId,
        ]);
        $row = $statement->fetch();

        return is_array($row) ? $this->findBookingContext((string) $row['id']) : null;
    }

    /**
     * @return array{course: array<string, mixed>, booking: array<string, string>}|null
     */
    private function findBookingContext(string $bookingId): ?array
    {
        $statement = $this->pdo()->prepare(<<<'SQL'
SELECT b.*
FROM fl_bookings b
WHERE b.id = :id
SQL);
        $statement->execute(['id' => $bookingId]);
        $booking = $statement->fetch();
        if (!is_array($booking)) {
            return null;
        }

        $course = $this->find((string) $booking['course_id']);
        if ($course === null) {
            return null;
        }

        $bookings = array_values(array_filter(
            $course['bookings'],
            static fn (array $candidate): bool => $candidate['id'] === $bookingId
        ));

        return [
            'course' => $course,
            'booking' => $bookings[0] ?? [
                'id' => (string) $booking['id'],
                'name' => (string) $booking['name'],
                'email' => (string) $booking['email'],
                'note' => (string) ($booking['note'] ?? ''),
                'paymentStatus' => (string) ($booking['payment_status'] ?? 'open'),
                'paymentConfirmedAt' => (string) ($booking['payment_confirmed_at'] ?? ''),
                'confirmationSentAt' => (string) ($booking['confirmation_sent_at'] ?? ''),
                'externalProvider' => (string) ($booking['external_provider'] ?? ''),
                'externalOrderId' => (string) ($booking['external_order_id'] ?? ''),
                'externalProductId' => (string) ($booking['external_product_id'] ?? ''),
                'externalPaidAt' => (string) ($booking['external_paid_at'] ?? ''),
                'createdAt' => (string) $booking['created_at'],
            ],
        ];
    }

    private function markBookingPaid(string $bookingId, string $paidAt): void
    {
        $paidAt = $paidAt !== '' ? $paidAt : date('c');
        $statement = $this->pdo()->prepare(<<<'SQL'
UPDATE fl_bookings
SET payment_status = 'paid',
    payment_confirmed_at = COALESCE(payment_confirmed_at, :payment_confirmed_at),
    external_paid_at = COALESCE(external_paid_at, :external_paid_at)
WHERE id = :id
SQL);
        $statement->execute([
            'payment_confirmed_at' => $this->toMysqlDateTime($paidAt) ?? $this->now(),
            'external_paid_at' => $this->toMysqlDateTime($paidAt),
            'id' => $bookingId,
        ]);
    }

    private function pdo(): PDO
    {
        return $this->databaseConnection->pdo();
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    private function toMysqlDateTime(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable($value))->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }
}
