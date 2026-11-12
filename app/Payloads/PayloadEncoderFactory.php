<?php

declare(strict_types=1);

namespace App\Payloads;

use App\Exceptions\ValidationException;

final class PayloadEncoderFactory
{
    /**
     * @var array<string, PayloadEncoderInterface>
     */
    private array $encoders = [];

    public function __construct()
    {
        $list = [
            new UrlPayload(),
            new TextPayload(),
            new EmailPayload(),
            new PhonePayload(),
            new SmsPayload(),
            new VCardPayload(),
            new WifiPayload(),
            new WhatsAppPayload(),
            new LocationPayload(),
            new MeCardPayload(),
            new EventPayload(),
            new BitcoinPayload(),
            new FacebookPayload(),
            new TwitterPayload(),
            new YouTubePayload(),
            new TelegramPayload(),
            new InstagramPayload(),
            new LinkedInPayload(),
            new PayPalPayload(),
            new AppLinkPayload(),
            new CustomUriPayload(),
        ];

        foreach ($list as $encoder) {
            $this->encoders[$encoder->type()] = $encoder;
        }
    }

    public function get(string $type): PayloadEncoderInterface
    {
        $key = strtolower(trim($type));
        if (!isset($this->encoders[$key])) {
            throw new ValidationException(sprintf('Unsupported QR content type "%s".', $type), [
                'type' => 'Please select one of the 21 supported QR content types.',
            ]);
        }

        return $this->encoders[$key];
    }

    /**
     * @return array<string, PayloadEncoderInterface>
     */
    public function all(): array
    {
        return $this->encoders;
    }

    /**
     * @return list<array{type: string, label: string, category: string, is_url_wrapper: bool, description: string, sample: array<string, mixed>}>
     */
    public function metadataCatalog(): array
    {
        $catalog = [];
        foreach ($this->encoders as $encoder) {
            $catalog[] = [
                'type' => $encoder->type(),
                'label' => $encoder->label(),
                'category' => $encoder->category(),
                'is_url_wrapper' => $encoder->isUrlWrapper(),
                'description' => $encoder->description(),
                'sample' => $encoder->sampleData(),
            ];
        }

        return $catalog;
    }
}
