<?php
/**
 * AbstractPleasingCommand.php
 */

namespace DevCoding\Pleasing\Tools\Command;

use Symfony\Component\Yaml\Yaml;
use DevCoding\Command\Base\AbstractConsole;
use XQ\Pleasing\Pleasing;

/**
 * Base class on which other Pleasing console commands are based.
 *
 * Class AbstractPleasingCommand
 *
 * @version v1.1.1
 * @author  Aaron M Jones <am@jonesiscoding.com>
 * @package DevCoding\Pleasing\Tools\Command
 */
class AbstractPleasingCommand extends AbstractConsole
{
  /** @var Pleasing */
  protected $_Pleasing;
  /** @var array */
  protected $defaultConfig = array();
  /** @var array */
  protected $paths = array();

  protected function getConfig()
  {
    if( $configFile = $this->io()->getOption( 'config' ) )
    {
      $config = $this->parseYaml( $configFile );
    }
    else
    {
      $config = $this->defaultConfig;
    }

    $this->paths[ 'kernel.root_dir' ]  = pathinfo( $configFile, PATHINFO_DIRNAME );
    $this->paths[ 'kernel.cache_dir' ] = '%kernel.root_dir%' . DIRECTORY_SEPARATOR . 'cache';

    if( !empty( $config[ 'paths' ] ) )
    {
      $this->paths = $config['paths'] + $this->paths;
    }
    $this->paths = $this->resolvePaths( $this->paths );

    return $this->resolvePaths( $config );

  }

  protected function resolvePaths( $config )
  {
    foreach( $config as $key => $value )
    {
      if( is_array( $value ) )
      {
        $value = $this->resolvePaths( $value );
      }
      else
      {
        $value = preg_replace_callback( "#(%([\w\.]+)%)#",
            function( $matches )
            {
              return ( array_key_exists( $matches[ 2 ], $this->paths ) ) ? $this->paths[ $matches[ 2 ] ] : $matches[ 0 ];
            },
            $value );
      }

      $config[$key] = $value;
    }

    return $config;
  }

  private function parseYaml($file)
  {
    if( is_readable( $file ) )
    {
      return Yaml::parse( file_get_contents( $file ) );
    }

    throw new \Exception( 'The given configuration file could not be read.' );
  }


}