<?php

namespace XQ\Pleasing\Tools\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use XQ\Pleasing\Pleasing;
use XQ\Pleasing\PleasingCompileTrait;

/**
 * Class PleasingPackageCommand
 * @package XQ\Pleasing\Command
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

    $AssetCollection = $this->Pleasing()->buildAssetCollection($collection, true);
    if ( $assetCode = $AssetCollection->dump() )
    {
      $this->validatedPath( pathinfo( $asset[ 'output' ], PATHINFO_DIRNAME ) );
      file_put_contents( $asset['output'], $assetCode );
      return true;
    }

    return false;
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