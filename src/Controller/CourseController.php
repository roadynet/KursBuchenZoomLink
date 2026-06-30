<?php

namespace App\Controller;

use App\Repository\CourseRepository;
use App\Service\ConfirmationMailer;
use DomainException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CourseController
{
    private const STATUSES = ['geplant', 'offen', 'ausgebucht'];

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        private readonly CourseRepository $courseRepository,
        private readonly ConfirmationMailer $confirmationMailer,
    ) {
    }

    #[Route('/', name: 'course_index', methods: ['GET'])]
    public function index(): Response
    {
        return new Response(file_get_contents($this->projectDir.'/templates/course/index.html'));
    }

    #[Route('/api/courses', name: 'course_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $courses = $this->courseRepository->all();
        if ($courses === []) {
            $courses = $this->courseRepository->reset($this->demoCourses());
        }

        return new JsonResponse($courses);
    }

    #[Route('/api/courses', name: 'course_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'Ungueltige JSON-Daten.'], Response::HTTP_BAD_REQUEST);
        }

        $course = $this->normalizeCourse($payload);
        if ($course === null) {
            return new JsonResponse(['error' => 'Bitte alle Pflichtfelder ausfuellen.'], Response::HTTP_BAD_REQUEST);
        }

        $course = $this->courseRepository->create($course);

        return new JsonResponse($course, Response::HTTP_CREATED);
    }

    #[Route('/api/courses/reset', name: 'course_reset', methods: ['POST'])]
    public function reset(): JsonResponse
    {
        $courses = $this->courseRepository->reset($this->demoCourses());

        return new JsonResponse($courses);
    }

    #[Route('/api/courses/{id}', name: 'course_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $this->courseRepository->delete($id);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/courses/{id}/zoom', name: 'course_zoom_sync', methods: ['POST'])]
    public function syncZoom(string $id): JsonResponse
    {
        $course = $this->courseRepository->syncZoom($id);

        return $course === null
            ? new JsonResponse(['error' => 'Kurs nicht gefunden.'], Response::HTTP_NOT_FOUND)
            : new JsonResponse($course);
    }

    #[Route('/api/courses/{id}/bookings', name: 'course_booking_create', methods: ['POST'])]
    public function book(Request $request, string $id): JsonResponse
    {
        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'Ungueltige JSON-Daten.'], Response::HTTP_BAD_REQUEST);
        }

        $booking = $this->normalizeBooking($payload);
        if ($booking === null) {
            return new JsonResponse(['error' => 'Bitte Name und gueltige E-Mail fuer die Buchung angeben.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $this->courseRepository->addBooking($id, $booking);
        } catch (DomainException $exception) {
            if ($exception->getMessage() === 'course-full') {
                return new JsonResponse(['error' => 'Dieser Kurs ist bereits ausgebucht.'], Response::HTTP_CONFLICT);
            }

            return new JsonResponse(['error' => 'Kurs nicht gefunden.'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($result, Response::HTTP_CREATED);
    }

    #[Route('/api/bookings/{id}/confirm-payment', name: 'booking_confirm_payment', methods: ['POST'])]
    public function confirmPayment(string $id): JsonResponse
    {
        try {
            $result = $this->courseRepository->confirmPayment($id);
        } catch (DomainException) {
            return new JsonResponse(['error' => 'Buchung nicht gefunden.'], Response::HTTP_NOT_FOUND);
        }

        $recipients = [];
        if (!$result['alreadySent']) {
            try {
                $recipients = $this->confirmationMailer->sendPaymentConfirmation($result['course'], $result['booking']);
                $this->courseRepository->markConfirmationSent($id);
                $result = $this->courseRepository->confirmPayment($id);
            } catch (\Throwable $exception) {
                return new JsonResponse([
                    'error' => 'Zahlung wurde markiert, aber die E-Mail konnte nicht versendet werden.',
                    'details' => $exception->getMessage(),
                    'course' => $result['course'],
                    'booking' => $result['booking'],
                ], Response::HTTP_BAD_GATEWAY);
            }
        }

        return new JsonResponse([
            'course' => $result['course'],
            'booking' => $result['booking'],
            'alreadySent' => $result['alreadySent'],
            'recipients' => $recipients,
        ]);
    }

    #[Route('/api/webhooks/tentary/paid-order', name: 'tentary_paid_order_webhook', methods: ['POST'])]
    public function tentaryPaidOrder(Request $request): JsonResponse
    {
        $payload = $this->webhookPayload($request);

        if (!$this->isTentaryWebhookConfigured()) {
            return new JsonResponse([
                'error' => 'Tentary/Zapier-Webhook ist noch nicht konfiguriert.',
                'hint' => 'Bitte TENTARY_WEBHOOK_SECRET in .env.local setzen.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        if (!$this->isTentaryWebhookAuthorized($request, $payload)) {
            return new JsonResponse(['error' => 'Webhook nicht autorisiert.'], Response::HTTP_UNAUTHORIZED);
        }

        $order = $this->normalizePaidOrder($payload);
        if ($order === null) {
            return new JsonResponse([
                'error' => 'Webhook-Daten unvollstaendig.',
                'required' => ['orderId', 'customerEmail', 'courseId oder courseTitle'],
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $this->courseRepository->addPaidBookingFromWebhook($order);
        } catch (DomainException $exception) {
            if ($exception->getMessage() === 'course-full') {
                return new JsonResponse(['error' => 'Dieser Kurs ist bereits ausgebucht.'], Response::HTTP_CONFLICT);
            }

            return new JsonResponse(['error' => 'Kurs fuer Webhook-Buchung nicht gefunden.'], Response::HTTP_NOT_FOUND);
        }

        $created = $result['created'];
        $recipients = [];
        if (!$result['alreadySent']) {
            try {
                $recipients = $this->confirmationMailer->sendPaymentConfirmation($result['course'], $result['booking']);
                $this->courseRepository->markConfirmationSent($result['booking']['id']);
                $result = $this->courseRepository->confirmPayment($result['booking']['id']);
            } catch (\Throwable $exception) {
                return new JsonResponse([
                    'error' => 'Webhook-Buchung wurde als bezahlt gespeichert, aber die E-Mail konnte nicht versendet werden.',
                    'details' => $exception->getMessage(),
                    'course' => $result['course'],
                    'booking' => $result['booking'],
                ], Response::HTTP_BAD_GATEWAY);
            }
        }

        return new JsonResponse([
            'source' => 'tentary',
            'created' => $created,
            'course' => $result['course'],
            'booking' => $result['booking'],
            'alreadySent' => $result['alreadySent'],
            'recipients' => $recipients,
        ], $created ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>|null
     */
    private function normalizeCourse(array $payload): ?array
    {
        $title = trim((string) ($payload['title'] ?? ''));
        $teacher = trim((string) ($payload['teacher'] ?? ''));
        $date = trim((string) ($payload['date'] ?? ''));
        $time = trim((string) ($payload['time'] ?? ''));
        $capacity = max(1, (int) ($payload['capacity'] ?? 0));
        $booked = min(max(0, (int) ($payload['booked'] ?? 0)), $capacity);
        $status = (string) ($payload['status'] ?? 'geplant');
        $zoomLink = trim((string) ($payload['zoomLink'] ?? ''));

        if ($title === '' || $teacher === '' || $date === '' || $time === '') {
            return null;
        }

        return [
            'id' => bin2hex(random_bytes(8)),
            'title' => $title,
            'teacher' => $teacher,
            'date' => $date,
            'time' => $time,
            'capacity' => $capacity,
            'booked' => $booked,
            'status' => $this->resolveStatus($booked, $capacity, $status),
            'zoomLink' => $zoomLink,
            'bookings' => [],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, string>|null
     */
    private function normalizeBooking(array $payload): ?array
    {
        $name = trim((string) ($payload['name'] ?? ''));
        $email = trim((string) ($payload['email'] ?? ''));
        $note = trim((string) ($payload['note'] ?? ''));

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return [
            'id' => bin2hex(random_bytes(8)),
            'name' => $name,
            'email' => $email,
            'note' => $note,
            'paymentStatus' => 'open',
            'paymentConfirmedAt' => '',
            'confirmationSentAt' => '',
            'createdAt' => date('c'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function webhookPayload(Request $request): array
    {
        $content = trim($request->getContent());
        if ($content !== '') {
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $request->request->all();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function isTentaryWebhookAuthorized(Request $request, array $payload): bool
    {
        $expected = $this->env('TENTARY_WEBHOOK_SECRET');
        $provided = trim((string) $request->headers->get('X-Federleicht-Webhook-Secret', ''));
        $authorization = trim((string) $request->headers->get('Authorization', ''));

        if ($provided === '' && str_starts_with($authorization, 'Bearer ')) {
            $provided = trim(substr($authorization, 7));
        }

        if ($provided === '') {
            $provided = $this->firstPayloadValue($payload, ['secret', 'webhookSecret', 'webhook_secret']);
        }

        return $provided !== '' && hash_equals($expected, $provided);
    }

    private function isTentaryWebhookConfigured(): bool
    {
        $secret = $this->env('TENTARY_WEBHOOK_SECRET');

        return $secret !== '' && $secret !== 'change_me';
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, string>|null
     */
    private function normalizePaidOrder(array $payload): ?array
    {
        $courseId = $this->firstPayloadValue($payload, ['courseId', 'course_id', 'kursId', 'kurs_id', 'metadata.courseId']);
        $courseTitle = $this->firstPayloadValue($payload, [
            'courseTitle',
            'course_title',
            'productTitle',
            'product_title',
            'productName',
            'product_name',
            'product.name',
            'title',
        ]);
        $email = $this->firstPayloadValue($payload, [
            'customerEmail',
            'customer_email',
            'customer.email',
            'buyerEmail',
            'buyer_email',
            'email',
        ]);
        $name = $this->firstPayloadValue($payload, [
            'customerName',
            'customer_name',
            'customer.name',
            'buyerName',
            'buyer_name',
            'name',
        ]);
        if ($name === '') {
            $firstName = $this->firstPayloadValue($payload, ['firstName', 'first_name', 'customer.first_name']);
            $lastName = $this->firstPayloadValue($payload, ['lastName', 'last_name', 'customer.last_name']);
            $name = trim($firstName.' '.$lastName);
        }
        if ($name === '') {
            $name = 'Kundin/Kunde';
        }

        $orderId = $this->firstPayloadValue($payload, [
            'orderId',
            'order_id',
            'order.id',
            'orderNumber',
            'order_number',
            'id',
        ]);
        $productId = $this->firstPayloadValue($payload, ['productId', 'product_id', 'product.id', 'sku']);
        $paidAt = $this->firstPayloadValue($payload, ['paidAt', 'paid_at', 'paid_date', 'order.paid_at', 'createdAt', 'created_at']);

        if ($orderId === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || ($courseId === '' && $courseTitle === '')) {
            return null;
        }

        return [
            'provider' => 'tentary',
            'orderId' => $orderId,
            'productId' => $productId,
            'courseId' => $courseId,
            'courseTitle' => $courseTitle,
            'customerName' => $name,
            'customerEmail' => $email,
            'paidAt' => $paidAt !== '' ? $paidAt : date('c'),
            'note' => $this->paidOrderNote($payload, $orderId, $productId),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function paidOrderNote(array $payload, string $orderId, string $productId): string
    {
        $parts = ['Tentary/Zapier Bestellung: '.$orderId];
        if ($productId !== '') {
            $parts[] = 'Produkt: '.$productId;
        }

        $amount = $this->firstPayloadValue($payload, ['amount', 'total', 'order.total', 'paidAmount', 'paid_amount']);
        $currency = $this->firstPayloadValue($payload, ['currency', 'order.currency']);
        if ($amount !== '') {
            $parts[] = trim($amount.' '.$currency);
        }

        $note = $this->firstPayloadValue($payload, ['note', 'customer_note', 'message']);
        if ($note !== '') {
            $parts[] = $note;
        }

        return implode(' | ', $parts);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, string>  $keys
     */
    private function firstPayloadValue(array $payload, array $keys): string
    {
        foreach ($keys as $key) {
            $value = $this->payloadValue($payload, $key);
            if (is_scalar($value)) {
                $value = trim((string) $value);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function payloadValue(array $payload, string $key): mixed
    {
        if (array_key_exists($key, $payload)) {
            return $payload[$key];
        }

        $current = $payload;
        foreach (explode('.', $key) as $part) {
            if (!is_array($current) || !array_key_exists($part, $current)) {
                return null;
            }

            $current = $current[$part];
        }

        return $current;
    }

    private function env(string $key): string
    {
        return trim((string) ($_ENV[$key] ?? $_SERVER[$key] ?? ''));
    }

    private function resolveStatus(int $booked, int $capacity, string $status): string
    {
        if ($booked >= $capacity) {
            return 'ausgebucht';
        }

        if (!in_array($status, self::STATUSES, true) || $status === 'ausgebucht') {
            return 'offen';
        }

        return $status;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function demoCourses(): array
    {
        return [
            [
                'id' => bin2hex(random_bytes(8)),
                'title' => 'Kreatives Schreiben',
                'teacher' => 'Anna Weber',
                'date' => '2026-06-12',
                'time' => '17:30',
                'capacity' => 12,
                'booked' => 3,
                'status' => 'offen',
                'zoomLink' => '',
                'bookings' => [
                    $this->demoBooking('Maria Keller', 'maria.keller@example.com', 'Kommt online dazu.'),
                    $this->demoBooking('Thomas Berg', 'thomas.berg@example.com', ''),
                    $this->demoBooking('Selin Aydin', 'selin.aydin@example.com', 'Braucht Rechnung.'),
                ],
            ],
            [
                'id' => bin2hex(random_bytes(8)),
                'title' => 'Atem und Stimme',
                'teacher' => 'Mara Schulz',
                'date' => '2026-06-18',
                'time' => '18:00',
                'capacity' => 4,
                'booked' => 4,
                'status' => 'ausgebucht',
                'zoomLink' => '',
                'bookings' => [
                    $this->demoBooking('Jana Roth', 'jana.roth@example.com', ''),
                    $this->demoBooking('Peter Lang', 'peter.lang@example.com', ''),
                    $this->demoBooking('Nina Vogt', 'nina.vogt@example.com', 'Wartet auf Teilnahmebestaetigung.'),
                    $this->demoBooking('Omar Haddad', 'omar.haddad@example.com', ''),
                ],
            ],
            [
                'id' => bin2hex(random_bytes(8)),
                'title' => 'Sommeratelier',
                'teacher' => 'Leo Braun',
                'date' => '2026-07-02',
                'time' => '16:00',
                'capacity' => 14,
                'booked' => 3,
                'status' => 'geplant',
                'zoomLink' => '',
                'bookings' => [
                    $this->demoBooking('Clara Hoffmann', 'clara.hoffmann@example.com', ''),
                    $this->demoBooking('Ben Fischer', 'ben.fischer@example.com', ''),
                    $this->demoBooking('Lena Schmitt', 'lena.schmitt@example.com', 'Nimmt mit Tablet teil.'),
                ],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function demoBooking(string $name, string $email, string $note): array
    {
        return [
            'id' => bin2hex(random_bytes(8)),
            'name' => $name,
            'email' => $email,
            'note' => $note,
            'paymentStatus' => 'open',
            'paymentConfirmedAt' => '',
            'confirmationSentAt' => '',
            'createdAt' => date('c'),
        ];
    }
}
