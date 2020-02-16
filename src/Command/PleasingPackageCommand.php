<?php

namespace DevCoding\Pleasing\Tools\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use XQ\Pleasing\Pleasing;
use XQ\Pleasing\Traits\PleasingCompileTrait;

/**
 * Class PleasingPackageCommand
 * @package DevCoding\Pleasing\Command
 */
class PleasingPackageCommand extends AbstractPleasingCommand
{
  use PleasingCompileTrait;

  const USE_CONFIG = '__USECONFIG__';

  protected $config = array();


  public function configure()
  {
    $this->setName('package')
      ->addArgument('src',InputArgument::REQUIRED,'The source file to package for distribution.')
      ->addArgument('dest', InputArgument::OPTIONAL, 'The destination directory.')
      ->addOption('config','c',InputOption::VALUE_REQUIRED, 'The configuration to use.')
      ->setDescription('Packages files for distribution.');
  }

  public function interact( InputInterface $input, OutputInterface $output )
  {
    if( $src = $input->getArgument( 'src' ) )
    {
      $ext = pathinfo( $src, PATHINFO_EXTENSION );

      if( $ext == 'yml' )
      {
        $input->setOption( 'config', $src );
        $input->setArgument( 'src', self::USE_CONFIG );
      }
      else
      {
        if( !$input->getArgument( 'dest' ) )
        {
          $input->setArgument( 'dest', pathinfo( $src, PATHINFO_DIRNAME ) );
        }
      }
    }
  }

  public function execute( InputInterface $input, OutputInterface $output )
  {
    // Get Config Together
    $config = $this->getConfig();

    // Add Asset Configuration to
    if( $src = $this->io()->getArgument( 'src' ) )
    {
      if( !$src = self::USE_CONFIG )
      {
        $config['assets']['default'] = array(
            'inputs' => array( $src ),
            'output' => $this->io()->getArgument( 'dest' ),
        );
      }
    }


    $errors = null;

    $this->io()->infoln( 'Building Assets...' );
    foreach( $config['pleasing'][ 'assets' ] as $name => $asset )
    {
      $this->io()->msg( '  ' . $name . '...' );
      if( $this->buildAsset($asset) )
      {
        $this->io()->successln( 'DONE!' );
      }
      else
      {
        $this->io()->errorln( 'ERROR!' );
        $errors[ $name ] = "Error building asset.";
      }
    }


    if( empty($errors) )
    {
      return self::EXIT_SUCCESS;
    }
    else
    {
      return self::EXIT_ERROR;
    }
  }

  protected function buildAsset( $asset )
  {
    $filters = (isset($asset[ 'filters' ])) ? $asset['filters'] : array();
    $collection = array();

    foreach ( $asset[ 'inputs' ] as $input )
    {
      $collection[] = $this->Pleasing()->buildFileAsset( $input, $filters );
    }

    $AssetCollection = $this->Pleasing()->buildAssetCollection($collection, $asset['output']);
    if ( $assetCode = $AssetCollection->dump() )
    {
      $this->validatedPath( pathinfo( $asset[ 'output' ], PATHINFO_DIRNAME ) );
      file_put_contents( $asset['output'], $assetCode );
      return true;
    }

    return false;
  }

  protected function getConfig()
  {
    $config = parent::getConfig();

    if(!array_key_exists('filters', $config))
    {
      $config['filters'] = array();
    }

    if(!array_key_exists('minify', $config['filters'])) {
      $config['filters']['minify'] = array( 'class' => PleasingMinifyFilter::class );
    }

    if(!array_key_exists('scss', $config['filters'])) {
      $config['filters']['scss'] = array(
        'bin' => '/usr/bin/sassc',
        'class' => PleasingSassFilter::class,
        'apply_to' => '\.scss',
      );
    }

    if(!array_key_exists('prefix')) {
      $config['filters']['prefix'] = array(
        'class' => PleasingPrefixFilter::class,
        'apply_to' => '\.scss|\.css'
      );
    }

    return $config;
  }

  private function validateConfig( $config )
  {
    return true;
  }

  /**
   * @return Pleasing
   */
  private function Pleasing()
  {
    if( !$this->_Pleasing instanceof Pleasing )
    {
      $config = $this->getConfig();

      $this->_Pleasing = new Pleasing($config, 'prod', $this->paths);
    }

    return $this->_Pleasing;
  }

}