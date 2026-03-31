<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\CheckoutInvite;
use App\Repository\CheckoutInviteRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-checkout-link',
    description: 'Generate a one-time checkout invite link for creating a new instance',
)]
class GenerateCheckoutLinkCommand extends Command
{
    public function __construct(
        private readonly CheckoutInviteRepository $checkoutInviteRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'base-url',
            null,
            InputOption::VALUE_OPTIONAL,
            'Base URL of the frontend application',
            'http://app.mydashboard.local'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $hash = bin2hex(random_bytes(32));

        $invite = new CheckoutInvite();
        $invite->setHash($hash);

        $this->checkoutInviteRepository->save($invite, true);

        $baseUrl = rtrim((string) $input->getOption('base-url'), '/');
        $link    = sprintf('%s/checkout/%s', $baseUrl, $hash);

        $io->success('Checkout invite link generated:');
        $io->writeln($link);
        $io->note('This link is single-use. Share it with the customer to set up their instance.');

        return Command::SUCCESS;
    }
}
