<?php

declare(strict_types=1);

namespace app\database;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class MakeMigrationCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('make:migration')
            ->setDescription('Cria um arquivo de migration com nome no formato YYYYMMDDHHmmss_nome.php')
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'Nome da migration em snake_case (ex: create_users_table)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name      = $input->getArgument('name');
        $timestamp = date('YmdHis');
        $className = 'Version' . $timestamp;
        $fileName  = $timestamp . '_' . $name . '.php';
        $dir       = dirname(__DIR__) . '/database/migration';
        $filePath  = $dir . '/' . $fileName;

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_exists($filePath)) {
            $output->writeln("<error>Arquivo já existe: {$filePath}</error>");
            return Command::FAILURE;
        }

        file_put_contents($filePath, $this->buildTemplate($className, $name));

        $output->writeln('');
        $output->writeln("<info>✔ Migration criada com sucesso!</info>");
        $output->writeln("<comment>  Arquivo : </comment>{$fileName}");
        $output->writeln("<comment>  Classe  : </comment>{$className}");
        $output->writeln("<comment>  Caminho : </comment>app/database/migration/{$fileName}");
        $output->writeln('');

        return Command::SUCCESS;
    }

    private function buildTemplate(string $className, string $name): string
    {
        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace app\database\migration;

            use Doctrine\DBAL\Schema\Schema;
            use Doctrine\Migrations\AbstractMigration;

            final class {$className} extends AbstractMigration
            {
                public function getDescription(): string
                {
                    return '{$name}';
                }

                public function up(Schema \$schema): void
                {
                    // escreva aqui as alterações
                }

                public function down(Schema \$schema): void
                {
                    // escreva aqui o rollback do up()
                }
            }
            PHP;
    }
}
