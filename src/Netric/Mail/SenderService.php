<?php

namespace Netric\Mail;

use Aereus\Config\Config;
use Netric\Entity\ObjType\EmailMessageEntity;
use Netric\Log\LogInterface;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Service used for sending email messages
 */
class SenderService
{
    /**
     * Log
     *
     * @var Log
     */
    private LogInterface $log;

    /**
     * Mail section of the config
     *
     * @var Config
     */
    private Config $mailConfig;

    /**
     * Construct the sender service
     *
     * @param Log $log
     * @param Confit $mailConfig Is the [mail] section of our config
     */
    public function __construct(LogInterface $log, Config $mailConfig)
    {
        $this->log = $log;
        $this->mailConfig = $mailConfig;
    }

    /**
     * Send a single email with raw data
     *
     * NOTE: Under the hood this uses PHPs mail() function so make sure we
     * have sendmail and/or the right SMTP settings in php.ini
     *
     * @param string $toAddress The address (or addresses) to send to
     * @param string $subject Message subject
     * @param string $body The raw body of the message
     * @param array $headers Any additional headers
     * @return bool true if successs, false on failure with details written to the log
     */
    public function send(
        string $toAddress,
        string $toDisplay,
        string $fromAddress,
        string $fromDisplay,
        string $subject,
        string $body,
        array $headers = []
    ): bool {
        //Create an instance; passing `true` enables exceptions
        $mail = new PHPMailer(true);

        $from = isset($fromAddress) ? $fromAddress : $this->mailConfig->noreply;
        $this->log->info("SenderService->send: sending email to $toAddress, from $fromAddress");

        try {
            //Server settings

            //Enable verbose debug output
            // $mail->SMTPDebug = SMTP::DEBUG_SERVER;

            // Send using SMTP
            $mail->isSMTP();

            // Set the SMTP server to send through
            // We wrap this in gethostbyname because of a bug with IPV6 and php
            // @see https://netcorecloud.com/tutorials/phpmailer-smtp-error-could-not-connect-to-smtp-host/
            $mail->Host = gethostbyname($this->mailConfig->server);
            $mail->SMTPAuth = false;
            $mail->SMTPAutoTLS = false;

            // Set SMTP authentication
            if ($this->mailConfig->username && $this->mailConfig->password) {
                $mail->SMTPAuth = true;
                $mail->Username = $this->mailConfig->username;
                $mail->Password = $this->mailConfig->password;
            }

            // Enable implicit TLS encryption
            // $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            // TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`
            $mail->Port       = $this->mailConfig->port;

            // Recipients
            $mail->setFrom($from, $fromDisplay);

            // Add a recipient (second param can be a display name if available)
            $mail->addAddress($toAddress, $toDisplay);

            // $mail->addReplyTo('info@example.com', 'Information');
            // $mail->addCC('cc@example.com');
            // $mail->addBCC('bcc@example.com');

            /// Add attachments
            // $mail->addAttachment('/var/tmp/file.tar.gz');
            // $mail->addAttachment('/tmp/image.jpg', 'new.jpg'); //Optional name

            // Set email format to HTML
            // $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            // $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

            if (isset($headers['message-id'])) {
                $mail->MessageID = '<' . $headers['message-id'] . '>';
            }

            return $mail->send();
        } catch (PHPMailerException $e) {
            $this->log->error(
                "SenderService->send: Mailer Error: " .
                    $mail->ErrorInfo
            );
            return false;
        }
    }

    /**
     * Send email from an email entity
     *
     * @param EmailMessageEntity $message
     * @return bool
     */
    public function sendEmailMessage(EmailMessageEntity $message): bool
    {
        // Create mailer instance for sending
        $mail = $this->createConfiguredPhpMailer();

        $from = !empty($message->getValue('from')) ? $message->getValue('from') : $this->mailConfig->noreply;
        $this->log->info(
            "SenderService->sendEmailMessage: sending email to " .
                $message->getValue('to') .
                ", from $from"
        );

        // From and reply to addresses
        $fromParts = $message->getFromData();
        if ($fromParts !== null) {
            $mail->setFrom($fromParts['address'], $fromParts['display']);
        }

        $replyToParts = $message->getReplyToData();
        if ($replyToParts !== null) {
            $mail->addReplyTo($replyToParts['address'], $replyToParts['display']);
        }

        // Add 'to' recipients (second param can be a display name if available)
        $recipients = $message->getToData();
        foreach ($recipients as $addressParts) {
            $mail->addAddress($addressParts['address'], $addressParts['display']);
        }

        // Add 'cc'
        $recipients = $message->getCcData();
        foreach ($recipients as $addressParts) {
            $mail->addCC($addressParts['address'], $addressParts['display']);
        }

        // Add 'bcc'
        $recipients = $message->getBccData();
        foreach ($recipients as $addressParts) {
            $mail->addBCC($addressParts['address'], $addressParts['display']);
        }

        /// Add attachments
        // $mail->addAttachment('/var/tmp/file.tar.gz');
        // $mail->addAttachment('/tmp/image.jpg', 'new.jpg'); //Optional name

        $mail->Subject = $message->getValue('subject');
        $mail->Body = $message->getValue('body');

        // Set email format to HTML and add a plain alt body
        if ($message->getValue('body_type') == EmailMessageEntity::BODY_TYPE_HTML) {
            $mail->isHTML(true);
            $mail->AltBody = $message->getPlainBody();
        }

        if (!empty($message->getValue('message_id'))) {
            $mail->MessageID = '<' . $message->getValue('message_id') . '>';
        }

        try {
            return $mail->send();
        } catch (PHPMailerException $e) {
            $this->log->error(
                "SenderService->send: Mailer Error: " .
                    $mail->ErrorInfo
            );
            return false;
        }
    }

    /**
     * Handle sending a bulk email message
     * @return bool true on success, false on failure with $this->getLastError set
     */
    public function sendFromTemplate(string $contactId, string $emailTemplateId)
    {
        $this->log->error("sendFromTemplate was called and it should not have been");
        return false;
    }

    /**
     * Create a new phpmailer instance and setup for sending
     *
     * @return PHPMailer
     */
    private function createConfiguredPhpMailer(): PHPMailer
    {
        //Create an instance; passing `true` enables exceptions
        $mail = new PHPMailer(true);

        //Enable verbose debug output
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER;

        // Send using SMTP
        $mail->isSMTP();

        // Set the SMTP server to send through
        // We wrap this in gethostbyname because of a bug with IPV6 and php
        // @see https://netcorecloud.com/tutorials/phpmailer-smtp-error-could-not-connect-to-smtp-host/
        $mail->Host = gethostbyname($this->mailConfig->server);
        $mail->SMTPAuth = false;
        $mail->SMTPAutoTLS = false;

        // Set SMTP authentication
        if ($this->mailConfig->username && $this->mailConfig->password) {
            $mail->SMTPAuth = true;
            $mail->Username = $this->mailConfig->username;
            $mail->Password = $this->mailConfig->password;
        }

        // Enable implicit TLS encryption
        // $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        // TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`
        $mail->Port = $this->mailConfig->port;
        return $mail;
    }
}
