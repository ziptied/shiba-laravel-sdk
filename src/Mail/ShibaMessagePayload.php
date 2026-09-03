<?php

declare(strict_types=1);

namespace Bentonow\ShibaLaravel\Mail;

use Bentonow\ShibaLaravel\Data\SendPayload;
use JsonException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Header\HeaderInterface;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\Mime\Part\DataPart;

final class ShibaMessagePayload
{
    public function fromSentMessage(SentMessage $sentMessage): SendPayload
    {
        $message = $sentMessage->getOriginalMessage();

        if (! $message instanceof Message) {
            throw new TransportException('PostShiba only supports structured Symfony MIME messages.');
        }

        $email = MessageConverter::toEmail($message);
        $from = $email->getFrom();

        return new SendPayload(
            from: $from === [] ? null : $from[0]->getAddress(),
            to: $this->addresses($email->getTo()),
            cc: $this->addresses($email->getCc()),
            bcc: $this->addresses($email->getBcc()),
            replyTo: $this->replyTo($email->getReplyTo()),
            subject: $email->getSubject(),
            text: $email->getTextBody(),
            html: $email->getHtmlBody(),
            headers: $this->headers($email->getHeaders()->all()),
            attachments: $this->attachments($email->getAttachments()),
            uniqueArgs: $this->uniqueArgs($email->getHeaders()->all()),
        );
    }

    /**
     * @param  list<Address>  $addresses
     * @return list<string>
     */
    private function addresses(array $addresses): array
    {
        return array_map(
            static fn (Address $address): string => $address->getAddress(),
            $addresses,
        );
    }

    /**
     * @param  list<Address>  $addresses
     */
    private function replyTo(array $addresses): ?string
    {
        $addresses = $this->addresses($addresses);

        return $addresses === [] ? null : implode(', ', $addresses);
    }

    /**
     * @param  iterable<string, HeaderInterface>  $headers
     * @return array<string, string>
     */
    private function headers(iterable $headers): array
    {
        $reserved = [
            'bcc',
            'cc',
            'content-transfer-encoding',
            'content-type',
            'date',
            'from',
            'message-id',
            'mime-version',
            'reply-to',
            'subject',
            'to',
            'x-capsule-unique-args',
        ];
        $result = [];

        foreach ($headers as $header) {
            $name = $header->getName();

            if (in_array(strtolower($name), $reserved, true)) {
                continue;
            }

            $value = preg_replace('/[\x00-\x1F\x7F]/', '', $header->getBodyAsString());

            if ($value !== null && $value !== '') {
                $result[$name] = $value;
            }
        }

        return $result;
    }

    /**
     * @param  iterable<string, HeaderInterface>  $headers
     * @return array<string, mixed>
     */
    private function uniqueArgs(iterable $headers): array
    {
        foreach ($headers as $header) {
            if (strcasecmp($header->getName(), 'X-Capsule-Unique-Args') !== 0) {
                continue;
            }

            try {
                $value = json_decode($header->getBodyAsString(), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return [];
            }

            return is_array($value) && ! array_is_list($value) ? $value : [];
        }

        return [];
    }

    /**
     * @param  list<DataPart>  $attachments
     * @return list<array{filename: string, content_type: string, content: string}>
     */
    private function attachments(array $attachments): array
    {
        return array_map(static fn (DataPart $attachment): array => [
            'filename' => $attachment->getFilename() ?: 'attachment',
            'content_type' => $attachment->getMediaType().'/'.$attachment->getMediaSubtype(),
            'content' => base64_encode($attachment->getBody()),
        ], $attachments);
    }
}
