<?php

namespace TreeHouse\BehatCommon;

use Behat\MinkExtension\Context\RawMinkContext;
use Behat\Symfony2Extension\Context\KernelAwareContext;
use PHPUnit_Framework_Assert as Assert;
use Symfony\Component\DomCrawler\Crawler;

class SwiftmailerContext extends RawMinkContext implements KernelAwareContext
{
    use KernelAwareTrait;

    /**
     * @var \Swift_Plugins_MessageLogger
     */
    private $logger;

    /**
     * @var \Swift_Message|null
     */
    private $message;

    /**
     * @param \Swift_Plugins_MessageLogger|null $logger
     */
    public function __construct(\Swift_Plugins_MessageLogger $logger = null) {
        $this->logger = $logger;
    }

    /**
     * @Then no email should have been sent
     */
    public function noEmailShouldHaveBeenSent()
    {
        $this->message = null;

        if (0 < $count = $this->getMailerLogger()->countMessages()) {
            throw new \RuntimeException(sprintf('Expected no email to be sent, but %d emails were sent.', $count));
        }
    }

    /**
     * @Then an email with subject :subject should have been sent
     * @Then an email with subject :subject should have been sent to :email
     */
    public function anEmailWithSubjectShouldHaveBeenSent($subject, $email = null)
    {
        $logger = $this->getMailerLogger();

        if (0 === $logger->countMessages()) {
            throw new \RuntimeException('No emails have been sent.');
        }

        $foundSubjects    = [];
        $foundToAddresses = [];

        /** @var \Swift_Message $message */
        foreach ($logger->getMessages() as $message) {
            $foundSubjects[] = $message->getSubject();

            if (preg_match(sprintf('~%s~ui', preg_quote($subject)), $message->getSubject())) {
                if (null === $email) {
                    // found, and to email isn't checked
                    $this->message = $email;

                    return;
                }

                $cc  = $message->getCc() ?: [];
                $bcc = $message->getBcc() ?: [];

                // remember which addresses we found
                $foundToAddresses = array_merge(
                    $foundToAddresses,
                    array_keys($message->getTo()),
                    array_keys($cc),
                    array_keys($bcc)
                );

                if (array_key_exists($email, $message->getTo()) || array_key_exists($email, $cc) || array_key_exists($email, $bcc)) {
                    // found, and to address matches
                    $this->message = $message;

                    return;
                }

                // check next message
                continue;
            }
        }

        if (!$foundToAddresses) {
            if (!empty($foundSubjects)) {
                throw new \RuntimeException(
                    sprintf(
                        'Subject "%s" was not found, but only these subjects: "%s"',
                        $subject,
                        implode('", "', array_unique($foundSubjects))
                    )
                );
            }

            // not found
            throw new \RuntimeException(sprintf('No message with subject "%s" found.', $subject));
        }

        throw new \RuntimeException(
            sprintf(
                'Subject found, but "%s" is not among to-addresses: %s',
                $email,
                implode(', ', array_unique($foundToAddresses))
            )
        );
    }

    /**
     * @Then the email header should contain :header
     */
    public function theEmailHeaderShouldContain($header)
    {
        Assert::assertTrue($this->message->getHeaders()->has($header));
    }

    /**
     * @Then the email header should contain :header with value :value
     */
    public function theEmailHeaderShouldContainWithValue($header, $value)
    {
        $this->theEmailHeaderShouldContain($header);

        Assert::assertEquals($value, $this->message->getHeaders()->get($header)->getFieldBody());
    }

    /**
     * @Then the email body should contain :text
     */
    public function theEmailBodyShouldContainText($text)
    {
        if (null === $this->message) {
            throw new \RuntimeException(
                'Select an email which has to have been sent first. ' .
                'You can use the step: "an email with subject :subject should have been sent (to :email)"'
            );
        }

        $crawler = new Crawler($this->message->getBody());

        Assert::assertContains($text, $crawler->text());
    }

    /**
     * @Then the email body should contain :number :element elements
     */
    public function theEmailBodyShouldContainNumElements($num, $element)
    {
        $num     = intval($num);
        $crawler = new Crawler($this->message->getBody());
        $found   = $crawler->filter($element);

        Assert::assertEquals($num, $found->count());
    }

    /**
     * @Then the email body should contain approximately :number :element elements
     * @Then the email body should contain ~ :number :element elements
     */
    public function theEmailBodyShouldContainApproximatelyNumElements($num, $element)
    {
        $num     = intval($num);
        $crawler = new Crawler($this->message->getBody());
        $found   = $crawler->filter($element);

        Assert::assertGreaterThanOrEqual($num - 1, $found->count());
        Assert::assertLessThanOrEqual($num + 1, $found->count());
    }

    /**
     * @Then the email body should not contain :text
     */
    public function theEmailBodyShouldNotContainText($text)
    {
        if (null === $this->message) {
            throw new \RuntimeException(
                'Select an email which has to have been sent first. ' .
                'You can use the step: "an email with subject :subject should have been sent (to :email)"'
            );
        }

        $crawler = new Crawler($this->message->getBody());

        Assert::assertNotContains($text, $crawler->text());
    }

    /**
     * @Then the email should have attachment :name
     */
    public function theEmailBodyShouldHaveAttachment($name)
    {
        Assert::assertTrue(in_array($name, $this->getEmailAttachments()), sprintf('File with name "%s" should be found in the attachment(s)', $name));
    }

    /**
     * @Then the email should not have attachment :name
     */
    public function theEmailBodyShouldNotHaveAttachment($name)
    {
        Assert::assertFalse(in_array($name, $this->getEmailAttachments()), sprintf('File with name "%s" should not be found in the attachment(s)', $name));
    }

    /**
     * @return array
     */
    protected function getEmailAttachments()
    {
        if (null === $this->message) {
            throw new \RuntimeException(
                'Select an email which has to have been sent first. ' .
                'You can use the step: "an email with subject :subject should have been sent (to :email)"'
            );
        }

        $files = [];

        /** @var \Swift_Mime_MimeEntity $child */
        foreach ($this->message->getChildren() as $child) {
            if (null !== $disposition = $child->getHeaders()->get('content-disposition')) {
                /** @var \Swift_Mime_Headers_ParameterizedHeader $disposition */
                $files[] = $disposition->getParameter('filename');
            }
        }

        return $files;
    }

    /**
     * @return \Swift_Plugins_MessageLogger
     */
    protected function getMailerLogger()
    {
        if (!$this->logger) {
            return $this->getContainer()->get('swiftmailer.mailer.default.plugin.messagelogger');
        }

        return $this->logger;
    }
}
