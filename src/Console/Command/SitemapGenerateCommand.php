<?php

declare(strict_types=1);

namespace Semitexa\Ssr\Console\Command;

use Semitexa\Core\Attribute\AsCommand;
use Semitexa\Core\Container\ContainerFactory;
use Semitexa\Core\Support\ProjectRoot;
use Semitexa\Ssr\Seo\AiSitemapLocator;
use Semitexa\Ssr\Seo\Sitemap\SitemapGenerationContext;
use Semitexa\Ssr\Seo\Sitemap\SitemapGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'sitemap:generate', description: 'Generate sitemap.xml from all registered providers')]
final class SitemapGenerateCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('sitemap:generate')
             ->setDescription('Generate sitemap.xml from all registered providers')
             ->addOption(
                 name: 'output',
                 shortcut: 'o',
                 mode: InputOption::VALUE_REQUIRED,
                 description: 'Output directory for generated sitemap files',
             )
             ->addOption(
                 name: 'base-url',
                 mode: InputOption::VALUE_REQUIRED,
                 description: 'Base URL for sitemap entries (auto-detected if not specified)',
             );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Sitemap Generation');

        try {
            $container = ContainerFactory::get();
            $generator = $container->get(SitemapGenerator::class);

            $outputDir = $input->getOption('output')
                ?? ProjectRoot::get() . '/var/sitemap';

            $baseUrl = $input->getOption('base-url')
                ?? AiSitemapLocator::originUrl();

            $context = new SitemapGenerationContext(baseUrl: $baseUrl);
            $result = $generator->generateAndWrite($context, $outputDir);

            if (!$result->success) {
                $io->error('Sitemap generation failed.');
                return Command::FAILURE;
            }

            $io->success(sprintf(
                'Sitemap generated: %d URLs in %d file(s) → %s',
                $result->totalUrls,
                $result->filesWritten,
                $result->primaryPath,
            ));
        } catch (\Throwable $e) {
            $io->error('sitemap:generate failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
