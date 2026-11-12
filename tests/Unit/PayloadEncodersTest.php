<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\ValidationException;
use App\Payloads\PayloadEncoderFactory;
use Tests\TestCase;

final class PayloadEncodersTest extends TestCase
{
    private PayloadEncoderFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var PayloadEncoderFactory $factory */
        $factory = $this->container['payload_factory'];
        $this->factory = $factory;
    }

    public function testAllTwentyOneSamplePayloadsEncodeValidly(): void
    {
        $encoders = $this->factory->all();
        self::assertCount(21, $encoders);

        foreach ($encoders as $type => $encoder) {
            $encoded = $encoder->encode($encoder->sampleData());
            self::assertNotEmpty($encoded, "Sample payload for {$type} should not be empty.");
        }
    }

    public function testWebsiteUrlNormalizationPreservesQueryAndFragment(): void
    {
        $encoder = $this->factory->get('url');
        $result = $encoder->encode(['url' => 'example.com/shop/item?id=42&ref=qr#pricing']);
        self::assertSame('https://example.com/shop/item?id=42&ref=qr#pricing', $result);
    }

    public function testUnicodeTextAndEmojiEncoding(): void
    {
        $encoder = $this->factory->get('text');
        $text = "مرحبا بالعالم 🌍\r\nLine 2 — Café";
        self::assertSame("مرحبا بالعالم 🌍\nLine 2 — Café", $encoder->encode(['text' => $text]));
    }

    public function testEmailPhoneAndSmsEncoding(): void
    {
        self::assertSame(
            'mailto:support@example.com?subject=Hello%20World&body=Line%201%0ALine%202',
            $this->factory->get('email')->encode([
                'email' => 'support@example.com',
                'subject' => 'Hello World',
                'body' => "Line 1\r\nLine 2",
            ])
        );

        self::assertSame(
            'tel:+14155552671',
            $this->factory->get('phone')->encode(['phone' => '+1 (415) 555-2671'])
        );

        self::assertSame(
            'sms:+14155552671?body=Order%20%2399%20ready%21',
            $this->factory->get('sms')->encode([
                'phone' => '+1 415 555 2671',
                'message' => 'Order #99 ready!',
            ])
        );
    }

    public function testVCardAndMeCardSpecialCharacterEscaping(): void
    {
        $vcard = $this->factory->get('vcard')->encode([
            'first_name' => 'Jane;Marie',
            'last_name' => 'O\'Connor, Jr.',
            'organization' => 'Acme\Labs',
            'notes' => "Line A\nLine B",
        ]);

        self::assertStringContainsString('BEGIN:VCARD', $vcard);
        self::assertStringContainsString('VERSION:3.0', $vcard);
        self::assertStringContainsString('N:O\'Connor\, Jr.;Jane\;Marie;;;', $vcard);
        self::assertStringContainsString('ORG:Acme\\\\Labs', $vcard);
        self::assertStringContainsString('NOTE:Line A\nLine B', $vcard);
        self::assertStringEndsWith('END:VCARD', $vcard);

        $mecard = $this->factory->get('mecard')->encode([
            'name' => 'Doe; John:Jr',
            'phone' => '+442079460958',
        ]);
        self::assertSame('MECARD:N:Doe\; John\:Jr;TEL:+442079460958;;', $mecard);
    }

    public function testWifiEscapingAndWpa3Mapping(): void
    {
        $wifi = $this->factory->get('wifi')->encode([
            'ssid' => 'My;Home:Net',
            'encryption' => 'WPA3',
            'password' => 'Pass\Word,123!',
            'hidden' => true,
        ]);

        self::assertSame('WIFI:T:WPA;S:My\;Home\:Net;P:Pass\\\\Word\,123!;H:true;;', $wifi);
    }

    public function testCalendarLocationAndBitcoinPayloads(): void
    {
        $ics = $this->factory->get('event')->encode([
            'title' => 'Release, Party; v1',
            'start' => '2027-05-01T10:00:00',
            'end' => '2027-05-01T11:30:00',
            'timezone' => 'UTC',
            'location' => 'Room 42',
        ]);
        self::assertStringContainsString('SUMMARY:Release\, Party\; v1', $ics);
        self::assertStringContainsString('DTSTART:20270501T100000Z', $ics);
        self::assertStringContainsString('DTEND:20270501T113000Z', $ics);

        $geo = $this->factory->get('location')->encode([
            'latitude' => '48.8584',
            'longitude' => '2.2945',
            'label' => 'Eiffel Tower',
        ]);
        self::assertSame('geo:48.8584,2.2945?q=48.8584,2.2945(Eiffel%20Tower)', $geo);

        $btc = $this->factory->get('bitcoin')->encode([
            'address' => 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq',
            'amount' => '0.12500000',
            'label' => 'Alice',
        ]);
        self::assertSame('bitcoin:bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq?amount=0.125&label=Alice', $btc);
    }

    public function testSocialAndDeepLinkValidationRejectsWrongDomains(): void
    {
        self::assertSame(
            'https://wa.me/14155552671?text=Hi%20there',
            $this->factory->get('whatsapp')->encode(['phone' => '+1 (415) 555-2671', 'message' => 'Hi there'])
        );
        self::assertSame('https://t.me/qubiq_dev', $this->factory->get('telegram')->encode(['target' => '@qubiq_dev']));
        self::assertSame('https://x.com/qubiq', $this->factory->get('twitter')->encode(['url' => '@qubiq']));

        $this->expectException(ValidationException::class);
        $this->factory->get('youtube')->encode(['url' => 'https://evil-phishing.example.com/watch?v=123']);
    }

    public function testCustomUriBlocksExecutableSchemes(): void
    {
        self::assertSame(
            'otpauth://totp/Qubiq:alice?secret=JBSWY3DPEHPK3PXP',
            $this->factory->get('custom_uri')->encode(['uri' => 'otpauth://totp/Qubiq:alice?secret=JBSWY3DPEHPK3PXP'])
        );

        $this->expectException(ValidationException::class);
        $this->factory->get('custom_uri')->encode(['uri' => 'javascript:alert(document.cookie)']);
    }
}
