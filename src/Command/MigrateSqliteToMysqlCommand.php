<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:migrate-sqlite-to-mysql',
    description: 'Exporte les données SQLite en SQL INSERT compatible MariaDB',
)]
class MigrateSqliteToMysqlCommand extends Command
{
    private const TABLES = ['users', 'leave_requests', 'leave_audit_logs'];

    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'Fichier de sortie (défaut : stdout)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $file   = $input->getOption('output');
        $handle = $file ? fopen($file, 'w') : STDOUT;

        if ($handle === false) {
            $io->error("Impossible d'ouvrir le fichier : $file");
            return Command::FAILURE;
        }

        $lines = [];
        $lines[] = '-- Migration SQLite → MariaDB';
        $lines[] = '-- Généré le ' . date('Y-m-d H:i:s');
        $lines[] = 'SET FOREIGN_KEY_CHECKS=0;';
        $lines[] = 'SET NAMES utf8mb4;';
        $lines[] = '';

        foreach (self::TABLES as $table) {
            $rows = $this->connection->fetchAllAssociative("SELECT * FROM $table");

            if (empty($rows)) {
                $lines[] = "-- Table $table : vide";
                $lines[] = '';
                continue;
            }

            $lines[] = "-- Table $table (" . count($rows) . ' lignes)';
            $lines[] = "TRUNCATE TABLE `$table`;";

            $columns = array_keys($rows[0]);
            $colList = implode(', ', array_map(fn($c) => "`$c`", $columns));

            foreach ($rows as $row) {
                $values = array_map(function ($v) {
                    if ($v === null) return 'NULL';
                    if (is_int($v) || is_float($v)) return (string) $v;
                    // Booléens SQLite stockés en 0/1
                    return "'" . addslashes((string) $v) . "'";
                }, array_values($row));

                $lines[] = "INSERT INTO `$table` ($colList) VALUES (" . implode(', ', $values) . ');';
            }

            $lines[] = '';
        }

        $lines[] = 'SET FOREIGN_KEY_CHECKS=1;';

        fwrite($handle, implode("\n", $lines) . "\n");

        if ($file) {
            fclose($handle);
            $io->success("Export généré : $file");
        } else {
            $io->writeln('', OutputInterface::VERBOSITY_VERBOSE);
        }

        return Command::SUCCESS;
    }
}
