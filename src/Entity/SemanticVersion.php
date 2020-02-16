<?php

namespace DevCoding\Pleasing\Tools\Entity;

use Composer\Semver\VersionParser;


/**
 * Class SemanticVersion
 * @package DevCoding\Pleasing\Entity
 */
class SemanticVersion
{
  const MAJOR = 1;
  const MINOR = 2;
  const PATCH = 3;
  const BUILD = 4;

  protected $major;
  protected $minor;
  protected $patch;
  protected $build;
  protected $raw;
  /** @var  VersionParser */
  protected $_VersionParser;


  // region //////////////////////////////////////////////// Init and Alias Methods

  public function __construct($version = null)
  {
    if( $version )
    {
      $this->setVersion( $version );
    }
  }

  public function setVersion( $version )
  {
    $parsed = $this->parseVersion( $version );
    if( !empty( $parsed ) )
    {
      $this->major = ( isset( $parsed[ 'major' ] ) ) ? $parsed[ 'major' ] : 0;
      $this->minor = ( isset( $parsed[ 'minor' ] ) ) ? $parsed[ 'minor' ] : 0;
      $this->patch = ( isset( $parsed[ 'patch' ] ) ) ? $parsed[ 'patch' ] : 0;
      $this->build = ( isset( $parsed[ 'build' ] ) ) ? $parsed[ 'build' ] : 0;
      $this->raw = ( isset( $parsed[ 'raw' ] ) ) ? $parsed[ 'raw' ] : 0;
    }
    elseif($version = '9999999-dev')
    {
      $this->major = 0;
      $this->minor = 0;
      $this->patch = 0;
      $this->build = 'dev-master';
      $this->raw = '9999999-dev';
    }
    else
    {
      throw new \Exception( 'Could not parse version number: '.$version );
    }

    return $this;
  }

  public function __toString()
  {
    return $this->getVersion();
  }

  // endregion ///////////////////////////////////////////// End Init & Alias Methods

  // region //////////////////////////////////////////////// Public Methods

  /**
   * Returns the complete version number.
   *
   * @return string
   */
  public function getVersion()
  {
    $version =
        $this->getMajor() . '.' .
        $this->getMinor() . '.' .
        $this->getPatch()
    ;

    if( $build = $this->getBuild() )
    {
      $version .= '-' . $build;
    }

    return $version;
  }

  /**
   * @return int
   */
  public function getMajor()
  {
    return $this->major;
  }

  /**
   * @return int
   */
  public function getMinor()
  {
    return $this->minor;
  }

  /**
   * @return int
   */
  public function getPatch()
  {
    return $this->patch;
  }

  /**
   * @return string
   */
  public function getBuild()
  {
    return $this->build;
  }

  public function getNext( $inc, $place = self::PATCH )
  {
    $major = $this->getMajor();
    $minor = $this->getMinor();
    $patch = $this->getPatch();
    switch( $place )
    {
      case self::MAJOR:
        $newVersion = $this->buildVersion( $major + $inc );
        break;
      case self::MINOR:
        $newVersion = $this->buildVersion( $major, $minor + $inc );
        break;
      case self::PATCH:
        $newVersion = $this->buildVersion( $major, $minor, $patch + $inc );
        break;
      case self::BUILD:
        $newVersion = $this->buildVersion( $major, $minor, $patch, $build = $inc );
        break;
      default:
        throw new \Exception( 'Invalid version part given to increment.' );
    }

    return new SemanticVersion( $newVersion );
  }

  public function getRaw()
  {
    return $this->raw;
  }

  // endregion ///////////////////////////////////////////// End Public Methods

  // region //////////////////////////////////////////////// Private Helper Methods

  /**
   * @param string $version
   *
   * @return array|null
   */
  private function parseVersion( $version )
  {
    if( $normalized = $this->normalizeVersion( $version ) )
    {
      if( preg_match( '#^(0|[1-9]\d*)\.(0|[1-9]\d*)\.?(0|[1-9]\d*)?\.?(0|[1-9]\d*)?(?:-([0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*))?(?:\+([0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*))?$#',
          $normalized,
          $matches ) )
      {
        $parsed[ 'major' ] = str_replace( 'v', '', $matches[ self::MAJOR ] );
        $parsed[ 'minor' ] = ( isset( $matches[ self::MINOR ] ) ) ? $matches[ self::MINOR ] : 0;
        $parsed[ 'patch' ] = ( isset( $matches[ self::PATCH ] ) ) ? $matches[ self::PATCH ] : 0;
        $parsed[ 'build' ] = ( isset( $matches[ self::BUILD ] ) ) ? $matches[ self::BUILD ] : 0;
        $parsed[ 'raw' ] = str_replace( 'v' . $parsed[ 'major' ], $parsed[ 'major' ], $version );

        return $parsed;
      }
    }

    return null;
  }

  /**
   * @param string $version
   *
   * @return string
   */
  private function normalizeVersion( $version )
  {
    if( !$this->_VersionParser instanceof VersionParser )
    {
      $this->_VersionParser = new VersionParser();
    }

    return $this->_VersionParser->normalize($version);
  }

  private function buildVersion( $major, $minor = 0, $patch = 0, $build = 0 )
  {
    $newVersion = $major . '.' .
                  $minor . '.' .
                  $patch . '.';

    return ( !empty( $build ) ) ? $newVersion . '-' . $build : $newVersion;
  }

  // endregion ///////////////////////////////////////////// End Private Helper Methods
}