<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'app:test-mail',
    description: 'Send a direct SMTP test email using the configured MAILER_DSN.'
)]
class TestMailCommand extends Command
{
    private const FROM_ADDRESS = 'albertbase09675695595@gmail.com';

    public function __construct(
        #[Autowire('%env(MAILER_DSN)%')]
        private string $mailerDsn
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('recipient', InputArgument::REQUIRED, 'Recipient email address')
            ->addOption('subject', null, InputOption::VALUE_OPTIONAL, 'Email subject', 'Sweetoria SMTP Test')
            ->addOption('from', null, InputOption::VALUE_OPTIONAL, 'From email address', self::FROM_ADDRESS);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $recipient = (string) $input->getArgument('recipient');
        $subject = (string) $input->getOption('subject');
        $from = (string) $input->getOption('from');

        try {
            $transport = Transport::fromDsn($this->mailerDsn);

            $email = (new Email())
                ->from($from)
                ->to($recipient)
                ->subject($subject)
                ->text("This is a Symfony mailer test message from Sweetoria.\n\nIf you received this email, the Brevo SMTP connection is working.");

            $transport->send($email);

            $output->writeln('<info>Mail sent successfully.</info>');
            $output->writeln(sprintf('Recipient: %s', $recipient));
            $output->writeln(sprintf('Subject: %s', $subject));

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln('<error>Mail test failed.</error>');
            $output->writeln($e->getMessage());

            return Command::FAILURE;
        }
    }
}
