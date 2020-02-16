<?php

namespace DevCoding\Pleasing\Tools\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use XQ\Pleasing\Filter\PleasingMinifyFilter;
use XQ\Pleasing\Filter\PleasingPrefixFilter;
use XQ\Pleasing\Filter\PleasingSassFilter;
use XQ\Pleasing\Pleasing;
use XQ\Pleasing\Traits\PleasingCompileTrait;

/**
 * Class PleasingPackageCommand
 * @package DevCoding\Pleasing\Command
 */
class PleasingPackageCommand extends AbstractPleasingCommand
{
  use PleasingCompileTrait;

  const USE_CONFIG    = '__USECONFIG__';
  const FILTER_CONFIG = array(
      'minify' => array( 'class' => PleasingMinifyFilter::class ),
      'sass'   => array(
          'bin'          => '',
          'import_paths' => array( 'vendor/' ),
          'output_style' => 'expanded',
          'class'        => PleasingSassFilter::class,
          'apply_to'     => '\.scss|\.sass',
      ),
      'prefix' => array(
          'class'    => PleasingPrefixFilter::class,
          'apply_to' => '\.scss|\.css'
      )
  );

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

  /**
   * Override to add default filters
   *
   * @return array
   */
  protected function getConfig()
  {
    return $this->mergeDefaultFilters( parent::getConfig() );
  }

  /**
   * Adds the default filters to the given pleasing configuration.
   *
   * If the configuration does not contain a filters array, the defaults are added.
   *
   * If the configuration contains a filters array, and the filters described in the configuration match the keys in
   * the defaults array, any missing keys in the defaults are added to the given configuration, but only if the
   * value in the 'class' key does not differ.
   *
   * @param array $config
   *
   * @return array
   */
  protected function mergeDefaultFilters( $config )
  {
    $filters = ( !empty( $config[ 'pleasing' ][ 'filters' ] ) ) ? $config[ 'pleasing' ][ 'filters' ] : array();
    $default = $this->getDefaultFilterConfig();

    if( empty( $filters ) )
    {
      $filters = $default;
    }
    else
    {
      foreach( $filters as $key => $filter )
      {
        if( !empty( $default[ $key ] ) )
        {
          $dFilter = $default[ $key ];
          if( empty( $filter[ 'class' ]) || $filter[ 'class' ] === $dFilter['class'] )
          {
            $filters[ $key ] = $dFilter + $filter;
          }
        }
      }
    }

    $config['pleasing']['filters'] = $filters;

    return $config;
  }

  /**
   * Retrieves the default filter configuration, and adds the path to the sassc binary, if it can be determined.
   *
   * @return array
   */
  protected function getDefaultFilterConfig()
  {
    $config = self::FILTER_CONFIG;

    if( !empty( $config[ 'sass' ] ) )
    {
      try
      {
        $config[ 'sass' ][ 'bin' ] = trim( $this->getBinaryPath( 'sassc' ) );
      }
      catch(\Exception $e)
      {

      }
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