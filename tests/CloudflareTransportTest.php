<?php

namespace Tomahock\CloudflareMail\Tests;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Tomahock\CloudflareMail\CloudflareTransport;

class CloudflareTransportTest extends TestCase
{
    // -------------------------------------------------------------------------
    // __toString
    // -------------------------------------------------------------------------

    public function test_string_representation_is_cloudflare(): void
    {
        $transport = new CloudflareTransport('account', 'token');

        $this->assertSame('cloudflare', (string) $transport);
    }

    // -------------------------------------------------------------------------
    // URL construction
    // -------------------------------------------------------------------------

    public function test_sends_to_correct_endpoint(): void
    {
        $transport = $this->makeTransport(
            responses: [$this->successResponse()],
            accountId: 'my-account',
        );

        $transport->send($this->basicEmail());

        $this->assertSame(
            'https://api.cloudflare.com/client/v4/accounts/my-account/email/sending/send',
            $this->lastRequestUri(),
        );
    }

    public function test_strips_trailing_slash_from_base_url(): void
    {
        $transport = $this->makeTransport(
            responses: [$this->successResponse()],
            accountId: 'acct',
            baseUrl: 'https://api.cloudflare.com/client/v4/',
        );

        $transport->send($this->basicEmail());

        $this->assertSame(
            'https://api.cloudflare.com/client/v4/accounts/acct/email/sending/send',
            $this->lastRequestUri(),
        );
    }

    public function test_uses_custom_base_url(): void
    {
        $transport = $this->makeTransport(
            responses: [$this->successResponse()],
            accountId: 'acct',
            baseUrl: 'https://custom.proxy.internal/v4',
        );

        $transport->send($this->basicEmail());

        $this->assertStringStartsWith(
            'https://custom.proxy.internal/v4/accounts/acct',
            $this->lastRequestUri(),
        );
    }

    // -------------------------------------------------------------------------
    // Authentication
    // -------------------------------------------------------------------------

    public function test_sets_bearer_authorization_header(): void
    {
        $transport = $this->makeTransport(
            responses: [$this->successResponse()],
            apiToken: 'my-secret-token',
        );

        $transport->send($this->basicEmail());

        $this->assertSame('Bearer my-secret-token', $this->lastRequestHeader('Authorization'));
    }

    public function test_sends_json_content_type(): void
    {
        $transport = $this->makeTransport([$this->successResponse()]);

        $transport->send($this->basicEmail());

        $this->assertStringContainsString('application/json', $this->lastRequestHeader('Content-Type'));
    }

    public function test_uses_post_method(): void
    {
        $transport = $this->makeTransport([$this->successResponse()]);

        $transport->send($this->basicEmail());

        $this->assertSame('POST', $this->requestHistory[0]['request']->getMethod());
    }

    // -------------------------------------------------------------------------
    // From / To address formatting
    // -------------------------------------------------------------------------

    public function test_from_plain_address(): void
    {
        $email = $this->basicEmail()->from('sender@example.com');
        $transport = $this->makeTransport([$this->successResponse()]);

        $transport->send($email);

        $this->assertSame('sender@example.com', $this->lastRequestPayload()['from']);
    }

    public function test_from_named_address(): void
    {
        $email = $this->basicEmail()->from(new Address('sender@example.com', 'Sender Name'));
        $transport = $this->makeTransport([$this->successResponse()]);

        $transport->send($email);

        $this->assertSame(
            ['email' => 'sender@example.com', 'name' => 'Sender Name'],
            $this->lastRequestPayload()['from'],
        );
    }

    public function test_to_plain_address(): void
    {
        $email = $this->basicEmail()->to('recipient@example.com');
        $transport = $this->makeTransport([$this->successResponse()]);

        $transport->send($email);

        $this->assertSame(['recipient@example.com'], $this->lastRequestPayload()['to']);
    }

    public function test_to_named_address(): void
    {
        $email = $this->basicEmail()->to(new Address('recipient@example.com', 'John Doe'));
        $transport = $this->makeTransport([$this->successResponse()]);

        $transport->send($email);

        $this->assertSame(
            [['email' => 'recipient@example.com', 'name' => 'John Doe']],
            $this->lastRequestPayload()['to'],
        );
    }

    public function test_multiple_recipients(): void
    {
        $email = $this->basicEmail()->to('a@example.com', 'b@example.com', 'c@example.com');
        $transport = $this->makeTransport([$this->successResponse()]);

        $transport->send($email);

        $to = $this->lastRequestPayload()['to'];
        $this->assertCount(3, $to);
        $this->assertContains('a@example.com', $to);
        $this->assertContains('b@example.com', $to);
        $this->assertContains('c@example.com', $to);
    }

    public function test_mixed_plain_and_named_recipients(): void
    {
        $email = $this->basicEmail()->to(
            'plain@example.com',
            new Address('named@example.com', 'Named'),
        );
        $transport = $this->makeTransport([$this->successResponse()]);

        $transport->send($email);

        $to = $this->lastRequestPayload()['to'];
        $this->assertContains('plain@example.com', $to);
        $this->assertContains(['email' => 'named@example.com', 'name' => 'Named'], $to);
    }

    // -------------------------------------------------------------------------
    // Subject
    // -------------------------------------------------------------------------

    public function test_subject_is_included(): void
    {
        $email = $this->basicEmail()->subject('My Test Subject');
        $transport = $this->makeTransport([$this->successResponse()]);

        $transport->send($email);

        $this->assertSame('My Test Subject', $this->lastRequestPayload()['subject']);
    }

    public function test_empty_subject_defaults_to_empty_string(): void
    {
        $email = (new Email)->from('a@a.com')->to('b@b.com')->text('body');
        $transport = $this->makeTransport([$this->successResponse()]);

        $transport->send($email);

        $this->assertSame('', $this->lastRequestPayload()['subject']);
    }

    // -------------------------------------------------------------------------
    // Body (text / html)
    // -------------------------------------------------------------------------

    public function test_includes_html_body(): void
    {
        $email = $this->basicEmail()->html('<p>Hello <strong>World</strong></p>');
        $transport = $this->makeTransport([$this->successResponse()]);

        $transport->send($email);

        $this->assertSame('<p>Hello <strong>World</strong></p>', $this->lastRequestPayload()['html']);
    }

    public function test_includes_text_body(): void
    {
        $email = $this->basicEmail()->text('Hello plain text');
        $transport = $this->makeTransport([$this->successResponse()]);

        $transport->send($email);

        $this->assertSame('Hello plain text', $this->lastRequestPayload()['text']);
    }

    public function test_includes_both_html_and_text(): void
    {
        $email = $this->basicEmail()->html('<p>Hi</p>')->text('Hi');
        $transport = $this->makeTransport([$this->successResponse()]);

        $transport->send($email);

        $payload = $this->lastRequestPayload();
        $this->assertArrayHasKey('html', $payload);
        $this->assertArrayHasKey('text', $payload);
    }

    public function test_omits_html_key_when_no_html_body(): void
    {
        $email = $this->basicEmail()->text('Plain only');
        $transport = $this->makeTransport([$this->successResponse()]);

        $transport->send($email);

        $this->assertArrayNotHasKey('html', $this->lastRequestPayload());
    }

    public function test_omits_text_key_when_no_text_body(): void
    {
        $email = $this->basicEmail()->html('<p>HTML only</p>');
        $transport = $this->makeTransport([$this->successResponse()]);

        $transport->send($email);

        $this->assertArrayNotHasKey('text', $this->lastRequestPayload());
    }

    // -------------------------------------------------------------------------
    // CC
    // -------------------------------------------------------------------------

    public function test_includes_single_cc(): void
    {
        $email = $this->basicEmail()->cc('cc@example.com');
        $transport = $this->makeTransport([$this->successResponse()]);

        $transport->send($email);

        $this->assertSame(['cc@example.com'], $this->lastRequestPayload()['cc']);
    }

    public function test_includes_multiple_cc(): void
    {
        $email = $this->basicEmail()->cc('cc1@example.com', 'cc2@example.com');
        $transport = $this->makeTransport([$this->successResponse()]);

        $transport->send($email);

        $cc = $this->lastRequestPayload()['cc'];
        $this->assertCount(2, $cc);
        $this->assertContains('cc1@example.com', $cc);
        $this->assertContains('cc2@example.com', $cc);
    }

    public function test_includes_named_cc(): void
    {
        $email = $this->basicEmail()->cc(new Address('cc@example.com', 'CC Person'));
        $transport = $this->makeTransport([$this->successResponse()]);

        $transport->send($email);

        $this->assertSame(
            [['email' => 'cc@example.com', 'name' => 'CC Person']],
            $this->lastRequestPayload()['cc'],
        );
    }

    public function test_omits_cc_key_when_not_set(): void
    {
        $transport = $this->makeTransport([$this->successResponse()]);

        $transport->send($this->basicEmail());

        $this->assertArrayNotHasKey('cc', $this->lastRequestPayload());
    }

    // -------------------------------------------------------------------------
    // BCC
    // -------------------------------------------------------------------------

    public function test_includes_single_bcc(): void
    {
        $email = $this->basicEmail()->bcc('bcc@example.com');
        $transport = $this->makeTransport([$this->successResponse()]);

        $transport->send($email);

        $this->assertSame(['bcc@example.com'], $this->lastRequestPayload()['bcc']);
    }

    public function test_includes_multiple_bcc(): void
    {
        $email = $this->basicEmail()->bcc('bcc1@example.com', 'bcc2@example.com');
        $transport = $this->makeTransport([$this->successResponse()]);

        $transport->send($email);

        $bcc = $this->lastRequestPayload()['bcc'];
        $this->assertCount(2, $bcc);
    }

    public function test_omits_bcc_key_when_not_set(): void
    {
        $transport = $this->makeTransport([$this->successResponse()]);

        $transport->send($this->basicEmail());

        $this->assertArrayNotHasKey('bcc', $this->lastRequestPayload());
    }

    // -------------------------------------------------------------------------
    // Reply-To
    // -------------------------------------------------------------------------

    public function test_includes_plain_reply_to(): void
    {
        $email = $this->basicEmail()->replyTo('reply@example.com');
        $transport = $this->makeTransport([$this->successResponse()]);

        $transport->send($email);

        $this->assertSame('reply@example.com', $this->lastRequestPayload()['reply_to']);
    }

    public function test_includes_named_reply_to(): void
    {
        $email = $this->basicEmail()->replyTo(new Address('reply@example.com', 'Support'));
        $transport = $this->makeTransport([$this->successResponse()]);

        $transport->send($email);

        $this->assertSame(
            ['email' => 'reply@example.com', 'name' => 'Support'],
            $this->lastRequestPayload()['reply_to'],
        );
    }

    public function test_omits_reply_to_key_when_not_set(): void
    {
        $transport = $this->makeTransport([$this->successResponse()]);

        $transport->send($this->basicEmail());

        $this->assertArrayNotHasKey('reply_to', $this->lastRequestPayload());
    }

    // -------------------------------------------------------------------------
    // Attachments
    // -------------------------------------------------------------------------

    public function test_omits_attachments_key_when_no_attachments(): void
    {
        $transport = $this->makeTransport([$this->successResponse()]);

        $transport->send($this->basicEmail());

        $this->assertArrayNotHasKey('attachments', $this->lastRequestPayload());
    }

    public function test_includes_single_attachment(): void
    {
        $email = $this->basicEmail()->attach('file content', 'report.pdf', 'application/pdf');
        $transport = $this->makeTransport([$this->successResponse()]);

        $transport->send($email);

        $attachments = $this->lastRequestPayload()['attachments'];
        $this->assertCount(1, $attachments);
    }

    public function test_attachment_content_is_base64_encoded(): void
    {
        $fileContent = 'binary file content here';
        $email = $this->basicEmail()->attach($fileContent, 'file.txt', 'text/plain');
        $transport = $this->makeTransport([$this->successResponse()]);

        $transport->send($email);

        $attachment = $this->lastRequestPayload()['attachments'][0];
        $this->assertSame(base64_encode($fileContent), $attachment['content']);
    }

    public function test_attachment_has_correct_filename(): void
    {
        $email = $this->basicEmail()->attach('content', 'my-document.pdf', 'application/pdf');
        $transport = $this->makeTransport([$this->successResponse()]);

        $transport->send($email);

        $this->assertSame('my-document.pdf', $this->lastRequestPayload()['attachments'][0]['filename']);
    }

    public function test_attachment_has_correct_mime_type(): void
    {
        $email = $this->basicEmail()->attach('content', 'image.png', 'image/png');
        $transport = $this->makeTransport([$this->successResponse()]);

        $transport->send($email);

        $this->assertSame('image/png', $this->lastRequestPayload()['attachments'][0]['type']);
    }

    public function test_includes_multiple_attachments(): void
    {
        $email = $this->basicEmail()
            ->attach('pdf content', 'report.pdf', 'application/pdf')
            ->attach('image data', 'photo.jpg', 'image/jpeg');

        $transport = $this->makeTransport([$this->successResponse()]);

        $transport->send($email);

        $this->assertCount(2, $this->lastRequestPayload()['attachments']);
    }

    public function test_multiple_attachments_have_correct_filenames(): void
    {
        $email = $this->basicEmail()
            ->attach('a', 'first.txt', 'text/plain')
            ->attach('b', 'second.txt', 'text/plain');

        $transport = $this->makeTransport([$this->successResponse()]);

        $transport->send($email);

        $filenames = array_column($this->lastRequestPayload()['attachments'], 'filename');
        $this->assertContains('first.txt', $filenames);
        $this->assertContains('second.txt', $filenames);
    }

    // -------------------------------------------------------------------------
    // Error handling
    // -------------------------------------------------------------------------

    public function test_throws_transport_exception_on_api_failure(): void
    {
        $transport = $this->makeTransport([
            $this->errorResponse([['code' => 1001, 'message' => 'Invalid sender address']]),
        ]);

        $this->expectException(TransportException::class);

        $transport->send($this->basicEmail());
    }

    public function test_exception_message_contains_api_error(): void
    {
        $transport = $this->makeTransport([
            $this->errorResponse([['code' => 1001, 'message' => 'Invalid sender address']]),
        ]);

        $this->expectExceptionMessage('Invalid sender address');

        $transport->send($this->basicEmail());
    }

    public function test_uses_first_error_message_when_multiple_errors(): void
    {
        $transport = $this->makeTransport([
            $this->errorResponse([
                ['code' => 1001, 'message' => 'First error'],
                ['code' => 1002, 'message' => 'Second error'],
            ]),
        ]);

        $this->expectExceptionMessage('First error');

        $transport->send($this->basicEmail());
    }

    public function test_uses_fallback_message_when_errors_array_is_empty(): void
    {
        $transport = $this->makeTransport([$this->errorResponse([])]);

        $this->expectExceptionMessage('Unknown Cloudflare API error');

        $transport->send($this->basicEmail());
    }

    public function test_throws_transport_exception_on_network_error(): void
    {
        $mock = new \GuzzleHttp\Handler\MockHandler([
            new ConnectException('Connection refused', new Request('POST', 'test')),
        ]);
        $stack = new \GuzzleHttp\HandlerStack($mock);
        $client = new \GuzzleHttp\Client(['handler' => $stack]);

        $transport = new CloudflareTransport(
            accountId: 'account',
            apiToken: 'token',
            client: $client,
        );

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Failed to reach Cloudflare Email Service');

        $transport->send($this->basicEmail());
    }

    public function test_wraps_network_error_as_previous_exception(): void
    {
        $connectException = new ConnectException('Connection refused', new Request('POST', 'test'));

        $mock = new \GuzzleHttp\Handler\MockHandler([$connectException]);
        $stack = new \GuzzleHttp\HandlerStack($mock);
        $client = new \GuzzleHttp\Client(['handler' => $stack]);

        $transport = new CloudflareTransport('account', 'token', client: $client);

        try {
            $transport->send($this->basicEmail());
            $this->fail('Expected TransportException was not thrown');
        } catch (TransportException $e) {
            $this->assertInstanceOf(ConnectException::class, $e->getPrevious());
        }
    }

    public function test_throws_on_500_response(): void
    {
        $transport = $this->makeTransport([
            $this->errorResponse([['code' => 500, 'message' => 'Internal server error']], 500),
        ]);

        $this->expectException(TransportException::class);

        $transport->send($this->basicEmail());
    }

    public function test_does_not_throw_on_success(): void
    {
        $transport = $this->makeTransport([$this->successResponse()]);

        $sentMessage = $transport->send($this->basicEmail());

        $this->assertNotNull($sentMessage);
    }

    // -------------------------------------------------------------------------
    // Payload structure
    // -------------------------------------------------------------------------

    public function test_payload_always_contains_required_keys(): void
    {
        $transport = $this->makeTransport([$this->successResponse()]);

        $transport->send($this->basicEmail());

        $payload = $this->lastRequestPayload();
        $this->assertArrayHasKey('from', $payload);
        $this->assertArrayHasKey('to', $payload);
        $this->assertArrayHasKey('subject', $payload);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function basicEmail(): Email
    {
        return (new Email)
            ->from('sender@example.com')
            ->to('recipient@example.com')
            ->subject('Test Subject')
            ->text('Hello World');
    }
}
