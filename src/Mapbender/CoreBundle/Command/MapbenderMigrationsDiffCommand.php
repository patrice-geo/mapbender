<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Mapbender\CoreBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Yaml;
use Doctrine\Bundle\DoctrineBundle\Command\Proxy\DoctrineCommandHelper;
use Doctrine\Bundle\MigrationsBundle\Command\DoctrineCommand;
use Doctrine\DBAL\Migrations\Tools\Console\Command\DiffCommand;
use Doctrine\Common\Proxy\Exception\InvalidArgumentException;

/**
 * Command to create a difference of a set of migrations.
 *
 * @author Paul Schmidt
 */
class MapbenderMigrationsDiffCommand extends DiffCommand
{

    protected function configure()
    {
        parent::configure();
        $this
            ->setName('mapbender:migrations:diff')
            ->addOption('db-mapbender-configuration', null, InputOption::VALUE_REQUIRED,
                'The path to db-mapbender-configuration.yml')
            ->addOption('app-module', null, InputOption::VALUE_REQUIRED,
                'The application module name from db-mapbender-configuration.yml')
            ->addOption('em', null, InputOption::VALUE_OPTIONAL, 'The entity manager to use for this command.');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$input->getOption('db-mapbender-configuration') || !file_exists($input->getOption('db-mapbender-configuration'))) {
            throw new InvalidArgumentException("The specified mapbender migrations file is not a valid file.");
        }
        $params = Yaml::parse($input->getOption('db-mapbender-configuration'));
        if (!is_array($params) || !isset($params['modules'])) {
            throw new InvalidArgumentException("The specified mapbender migrations file is not a valid mapbender migrations file.");
        }
        if (!$input->getOption('app-module') || !isset($params['modules'][$input->getOption('app-module')])) {
            throw new \Exception('The specified mapbender migrations file has no module: "' . $input->getOption('app-module') . '".');
        }
        $module = $params['modules'][$input->getOption('app-module')];
        DoctrineCommandHelper::setApplicationEntityManager($this->getApplication(), $input->getOption('em'));
        $configuration = $this->getMigrationConfiguration($input, $output);
//        $origSchemaFilter = $configuration->getConnection()->getConfiguration()->getFilterSchemaAssetsExpression();
        echo "Set schema fliter " . $module['schema_filter'] . "\n";
        $configuration->getConnection()->getConfiguration()->setFilterSchemaAssetsExpression($module['schema_filter']);
        DoctrineCommand::configureMigrations($this->getApplication()->getKernel()->getContainer(), $configuration);
        parent::execute($input, $output);
    }

}
