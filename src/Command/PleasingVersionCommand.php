<?php

namespace DevCoding\Pleasing\Tools\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use DevCoding\Command\Base\AbstractConsole;
use DevCoding\Pleasing\Tools\Entity\SemanticVersion;

/**
 * Increments version numbers found within the files of a project.
 *
 * Class PleasingVersionCommand
 *
 * @version v1.1
 * @author  Aaron M Jones <am@jonesiscoding.com>
 * @package XQ\Projects\Command
 */
class PleasingVersionCommand extends AbstractConsole
{
  protected $_Version;

  protected $composer;
  protected $composer_file;
  protected $folders = array( 'dist', 'js', 'less', 'css', 'src', 'scss', 'bin' );

  // region //////////////////////////////////////////////// Setup & Alias Methods

  /**
   * {@inheritdoc}
   */
  protected function configure()
  {
    $this
      ->setName('version')
      ->addArgument( 'src', InputArgument::OPTIONAL, 'The path in which to find files to version.', getcwd() )
      ->addOption('minor',null,InputOption::VALUE_NONE, 'Increment the version by one minor version.')
      ->addOption('major',null,InputOption::VALUE_NONE, 'Increment the version by one major version, and reset the minor and patch versions.',null)
      ->addOption('patch',null,InputOption::VALUE_NONE, 'Increment the version by one patch version.',null)
      ->addOption('build',null,InputOption::VALUE_REQUIRED, 'Build number to append to the current version number.')
      ->addOption('increment',null,InputOption::VALUE_REQUIRED, 'The amount to increment the given version by.', 1)
      ->addOption('set',null,InputOption::VALUE_REQUIRED, 'Set the version implicitly.')
      ->addOption('composer',null,InputOption::VALUE_REQUIRED, 'The path to the appropriate composer.json.')
      ->setDescription('Update the version number listed in all files of a package.');
  }

  // endregion ///////////////////////////////////////////// End Setup & Alias Methods

  // region //////////////////////////////////////////////// Command Methods

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $path = realpath( $this->io()->getArgument( 'src' ) );
    $this->io()->write( 'Retrieving current version number...' );
    $this->io()->commentln( ' v' . (string) $this->getVersion() );

    if( !$NewVersion = $this->getNewVersion() )
    {
      $this->io()->errorblk( 'Could not determine new version!' );

      return self::EXIT_ERROR;
    }
    else
    {
      $this->io()->write( 'Incrementing to ' );
      $this->io()->comment( ' v' . (string) $NewVersion );
      $this->io()->writeln('...');
    }

    // Loop through Files to Set Version Number
    $this->io()->writeln( 'Looking for Files...' );
    foreach ( $this->folders as $folder ) {
      $folder = $path . DIRECTORY_SEPARATOR . $folder;
      if( file_exists( $folder ) )
      {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($folder)) as $file)
        {
          $ext = pathinfo( $file, PATHINFO_EXTENSION );
          $filename = pathinfo( $file, PATHINFO_BASENAME );
          $displayPath = str_replace( $path, '', $file );
          if ( $ext == 'js' || $ext == 'less' || $ext == 'css' || $ext == 'php' || $filename == 'console' ) {
            $this->io()->msg( '  Updating ' . $displayPath . '...' );
            if ( $this->setVersionForFile( $file, (string) $NewVersion ) ) {
              $this->io()->successln( 'SUCCESS!' );
            }
            else
            {
              $this->io()->errorln( 'ERROR!' );
              $errors[] = $file;
            }
          }
        }
      }
    }

    // Update Composer
    $this->io()->msg( '  Updating composer.json...');
    if( $this->setVersionForComposer( (string) $NewVersion ) )
    {
      $this->io()->successln( 'SUCCESS!' );
    }
    else
    {
      $composer = $this->findComposerConfig();
      $this->io()->errorln( 'ERROR!' );
      $errors[] = ($composer) ? $composer : 'composer.json';
    }

    if( empty( $errors ) )
    {
      $this->io()->successblk( 'The version was successfully updated.' );

      return self::EXIT_SUCCESS;
    }
    else
    {
      $this->io()->errorblk( 'The following files had errors:' . implode( "\n", $errors ) );

      return self::EXIT_ERROR;
    }
  }

  // endregion ///////////////////////////////////////////// End Command Methods

  private function getNewVersion()
  {
    $inc = $this->io()->getOption( 'increment' );
    if( $set = $this->io()->getOption( 'set' ) )
    {
      $newVersion = new SemanticVersion( $set );
    }
    elseif( $this->io()->getOption( 'major' ) )
    {
      $newVersion = $this->getVersion()->getNext( $inc, SemanticVersion::MAJOR );
    }
    elseif( $this->io()->getOption( 'minor' ) )
    {
      $newVersion = $this->getVersion()->getNext( $inc, SemanticVersion::MINOR );
    }
    elseif( $this->io()->getOption( 'patch' ) )
    {
      $newVersion = $this->getVersion()->getNext( $inc, SemanticVersion::PATCH );
    }
    elseif( $build = $this->io()->getOption( 'build' ) )
    {
      $newVersion = $this->getVersion()->getNext( $build, SemanticVersion::BUILD);
    }

    return (isset($newVersion)) ? $newVersion : null;
  }

  private function getVersion()
  {
    if( $this->_Version instanceof SemanticVersion)
    {
      return $this->_Version;
    }
    else
    {
      $version = '';
      if( $composerConfig = $this->findComposerConfig() )
      {
        $json = json_decode( file_get_contents( $this->findComposerConfig() ), true );
        $version = ( isset( $json[ 'version' ] ) ) ? $json[ 'version' ] : null;
      }

      if( !$composerConfig || empty($json) || empty($version) )
      {
        throw new \Exception( 'Could not read current version!' );
      }
      else
      {
        $this->_Version = new SemanticVersion( $version );
      }
    }

    return $this->_Version;
  }

  private function setVersionForFile($filePath, $newVersion)
  {
    $rawVersion = $this->getVersion()->getRaw();
    if ( ($filePath = realpath( $filePath )) && !is_dir($filePath) ) {
      if( $contents = file_get_contents( $filePath ) )
      {
        if ( preg_match_all( '#/\*([^*]|[\r\n]|(\*+([^*/]|[\r\n])))*\*+/#', $contents, $comments ) ) {
          foreach ( $comments[ 0 ] as $comment ) {
            if ( strpos($comment,$rawVersion) !== false ) {
              $newComment = str_replace( $rawVersion, $newVersion, $comment );
              $contents = str_replace( $comment, $newComment, $contents );
            }
          }
          return file_put_contents( $filePath, $contents );
        }
        else
        {
          if( pathinfo( $filePath, PATHINFO_BASENAME ) == 'console')
          {
            $contents = str_replace( "'v" . $rawVersion . "'", "'v" . $newVersion . "'", $contents);

            return file_put_contents( $filePath, $contents );
          }
        }
      }
    }

    return false;
  }

  private function setVersionForComposer( $newVersion )
  {
    $composerFile = $this->findComposerConfig();
    $count = 0;
    $contents =
        str_replace( '"version": "' . $this->getVersion()->getRaw() . '",',
            '"version": "' . $newVersion . '",',
            file_get_contents( $composerFile ),
            $count
        );

    if( $count > 0 )
    {
      return file_put_contents( $composerFile, $contents );
    }

    return false;
  }

  private function findComposerConfig()
  {
    if( !$composer = $this->io()->getOption( 'composer' ) )
    {
      $src = $this->io()->getArgument('src');
      $composer =  $src . DIRECTORY_SEPARATOR . 'composer.json';
      if( !file_exists( $composer ) )
      {
        if( strpos( $src, 'src' ) !== false  )
        {
          $pathParts = explode( DIRECTORY_SEPARATOR, $src );
          $path = '';
          foreach( $pathParts as $pathPart )
          {
            $path .= DIRECTORY_SEPARATOR . $pathPart;
            if( $pathPart == 'src' )
            {
              $composer = $path . DIRECTORY_SEPARATOR . 'composer.json';
            }
          }
        }
      }
    }

    return ( file_exists( $composer ) ) ? $composer : null;
  }
}
