<?php

namespace App\Tests\Service;

use App\Service\ZoomMeetingProvider;
use PHPUnit\Framework\TestCase;

final class ZoomMeetingProviderTest extends TestCase
{
    protected function setUp(): void
    {
        foreach ([
            'ZOOM_API_KEY',
            'ZOOM_ACCOUNT_ID',
            'ZOOM_CLIENT_ID',
            'ZOOM_CLIENT_SECRET',
            'ZOOM_HOST_USER_ID',
        ] as $key) {
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }

    public function testCreatesDeterministicPlaceholderMeetingWithoutApiCredentials(): void
    {
        $provider = new ZoomMeetingProvider();

        $course = $provider->getOrCreateMeeting([
            'id' => 'course-writing-01',
            'title' => 'Kreatives Schreiben',
            'date' => '2026-07-02',
            'time' => '16:00',
        ]);

        self::assertSame('placeholder', $course['zoomProvider']);
        self::assertSame('placeholder-ready', $course['zoomStatus']);
        self::assertNotSame('', $course['zoomMeetingId']);
        self::assertNotSame('', $course['zoomPassword']);
        self::assertStringStartsWith('https://zoom.us/j/'.$course['zoomMeetingId'], $course['zoomLink']);
        self::assertStringContainsString('pwd='.$course['zoomPassword'], $course['zoomLink']);
    }

    public function testKeepsExistingApiMeetingData(): void
    {
        $provider = new ZoomMeetingProvider();

        $course = $provider->getOrCreateMeeting([
            'id' => 'course-existing-api',
            'title' => 'Atem und Stimme',
            'zoomProvider' => 'api',
            'zoomMeetingId' => '1234567890',
            'zoomPassword' => 'safe-demo',
            'zoomLink' => 'https://zoom.us/j/1234567890?pwd=safe-demo',
        ]);

        self::assertSame('api', $course['zoomProvider']);
        self::assertSame('api-ready', $course['zoomStatus']);
        self::assertSame('1234567890', $course['zoomMeetingId']);
        self::assertSame('safe-demo', $course['zoomPassword']);
        self::assertSame('https://zoom.us/j/1234567890?pwd=safe-demo', $course['zoomLink']);
    }
}
