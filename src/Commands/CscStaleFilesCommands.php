<?php

namespace Drupal\csc_admin\Commands;

use Drush\Commands\DrushCommands;
use Drupal\Core\Database\Connection;
use Drupal\file\Entity\File;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drush commands for cleaning up stale media file references.
 */
class CscStaleFilesCommands extends DrushCommands {

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * @var \Drupal\file\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * Construct the command object.
   */
  public function __construct(Connection $database, $file_url_generator) {
    parent::__construct();
    $this->database = $database;
    $this->fileUrlGenerator = $file_url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('file_url_generator')
    );
  }

  /**
   * List stale file references in media entities.
   *
   * @command csc:stale-files
   * @aliases stale-files
   * @usage drush csc:stale-files
   *   Lists stale file IDs, media IDs, and URIs.
   */
  public function listStaleFiles() {
    $query = <<<SQL
SELECT fu.fid,
       fu.id AS media_id,
       fm.uri AS file_uri
FROM drwt_file_usage fu
JOIN drwt_file_managed fm ON fm.fid = fu.fid
WHERE fu.type = 'media'
  AND fu.id IN (SELECT mid FROM drwt_media_field_data)
  AND fu.fid NOT IN (
    SELECT field_media_image_target_id
    FROM drwt_media__field_media_image
  )
ORDER BY fu.id
SQL;

    $results = $this->database->query($query)->fetchAll();

    if (empty($results)) {
      $this->io()->success('No stale files found.');
      return;
    }

    $rows = [];
    foreach ($results as $row) {
      $fid = $row->fid;
      $media_id = $row->media_id;
      $uri = $row->file_uri;

      // Try to generate a URL (if the file still exists).
      $url = '';
      $file = File::load($fid);
      if ($file) {
        $url = $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
      }

      $rows[] = [
        'fid' => $fid,
        'media_id' => $media_id,
        'uri' => $uri,
        'url' => $url,
      ];
    }

    $this->io()->table(['FID', 'Media ID', 'URI', 'URL'], $rows);
  }

}
