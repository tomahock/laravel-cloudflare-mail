<?php

namespace Tomahock\CloudflareMail;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\Mime\Part\DataPart;
use Tomahock\CloudflareMail\Exceptions\CloudflareMailException;

class CloudflareTransport extends AbstractTransport
{
    private Client $client;

    private string $sendUrl;

    public function __construct(
        private readonly string $accountId,
        private readonly string $apiToken,
        private readonly string $baseUrl = 'https://api.cloudflare.com/client/v4',
        int $timeout = 30,
        ?Client $client = null,
    ) {
        parent::__construct();

        $this->sendUrl = rtrim($baseUrl, '/') . "/accounts/{$accountId}/email/sending/send";

        $this->client = $client ?? new Client([
            'timeout' => $timeout,
            'headers' => [
                'Authorization' => "Bearer {$apiToken}",
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $envelope = $message->getEnvelope();

        $payload = $this->buildPayload($email, $envelope);

        try {
            $response = $this->client->post($this->sendUrl, ['json' => $payload]);
            $body = json_decode((string) $response->getBody(), true);

            if (! ($body['success'] ?? false)) {
                throw CloudflareMailException::fromApiResponse($body, $response->getStatusCode());
            }
        } catch (CloudflareMailException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        } catch (GuzzleException $e) {
            throw new TransportException(
                "Failed to reach Cloudflare Email Service: {$e->getMessage()}",
                0,
                $e,
            );
        }
    }

    private function buildPayload(Email $email, Envelope $envelope): array
    {
        $payload = [
            'from' => $this->formatAddress($envelope->getSender()),
            'to' => array_map($this->formatAddress(...), $envelope->getRecipients()),
            'subject' => $email->getSubject() ?? '',
        ];

        if ($html = $email->getHtmlBody()) {
            $payload['html'] = $html;
        }

        if ($text = $email->getTextBody()) {
            $payload['text'] = $text;
        }

        if ($ccAddresses = $email->getCc()) {
            $payload['cc'] = array_map($this->formatAddress(...), $ccAddresses);
        }

        if ($bccAddresses = $email->getBcc()) {
            $payload['bcc'] = array_map($this->formatAddress(...), $bccAddresses);
        }

        if ($replyTo = $email->getReplyTo()) {
            $payload['reply_to'] = $this->formatAddress($replyTo[0]);
        }

        $attachments = $this->buildAttachments($email);
        if ($attachments) {
            $payload['attachments'] = $attachments;
        }

        return $payload;
    }

    private function formatAddress(Address $address): array|string
    {
        if ($name = $address->getName()) {
            return ['email' => $address->getAddress(), 'name' => $name];
        }

        return $address->getAddress();
    }

    private function buildAttachments(Email $email): array
    {
        $attachments = [];

        foreach ($email->getAttachments() as $attachment) {
            if (! $attachment instanceof DataPart) {
                continue;
            }

            $item = [
                'filename' => $attachment->getFilename() ?? 'attachment',
                'content' => base64_encode($attachment->getBody()),
                'type' => $attachment->getMediaType() . '/' . $attachment->getMediaSubtype(),
            ];

            $attachments[] = $item;
        }

        return $attachments;
    }

    public function __toString(): string
    {
        return 'cloudflare';
    }
}
