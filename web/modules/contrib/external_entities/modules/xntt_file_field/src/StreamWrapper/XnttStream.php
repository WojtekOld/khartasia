<?php

namespace Drupal\xntt_file_field\StreamWrapper;

use Drupal\Core\StreamWrapper\LocalStream;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\external_entities\ExternalEntityStorage;
use Drupal\link\Plugin\Field\FieldType\LinkItem;

/**
 * Defines a class for external entities stream wrapper.
 *
 * This class provides a complete stream wrapper implementation. URIs such as
 * "xntt://xntt_type/xntt_instance_id/file_field_name(/delta)" are expanded to
 * the corresponding external entity file URI.
 *
 * LocalStream uses non lowerCamel format so we disable syntax checking.
 *
 * phpcs:ignoreFile
 */
class XnttStream extends LocalStream {

  /**
   * Regular expression to match a valid XNTT stream (use '#' regex delimiters).
   *
   * "xntt://<content type machine name>/<content identifier>/<uri drupal field machine name>/<file number>/<file name>"
   *
   * @var string
   */
  const XNTT_STREAM_REGEX = 'xntt://([a-z_]\w*)/(.+?)/([a-z_]\w*)/(\d+)/([^\/]+)';

  /**
   * Stream context resource.
   *
   * @var resource
   */
  public $context;

  /**
   * A generic resource handle.
   *
   * @var resource
   */
  public $handle = NULL;

  /**
   * Instance URI (stream).
   *
   * A stream is referenced as "scheme://target".
   *
   * @var string
   */
  protected $uri;

  /**
   * External file URI (stream).
   *
   * @var string
   */
  protected $realUri;

  /**
   * The current request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The current external entity object.
   *
   * @var \Drupal\external_entities\Entity\ExternalEntity
   */
  protected $xntt;

  /**
   * The current offset.
   *
   * @var int
   */
  protected $offset;

  /**
   * The file content in memory.
   *
   * @var string
   */
  protected $data;

  /**
   * Tells if the given URL is using 'xntt://' scheme.
   */
  public function isXnttScheme($uri) {
    if (empty($uri)) {
      return FALSE;
    }
    return (0 === stripos($uri, 'xntt://'));
  }

  /**
   * {@inheritdoc}
   */
  public function setUri($uri) {
    if (!preg_match('#^' . static::XNTT_STREAM_REGEX . '$#', $uri)) {
      // The stream protocol ('xntt://') was not found in $uri, malformed $uri
      // passed.
      throw new \InvalidArgumentException(
        "Malformed uri parameter passed (not starting by 'xntt://'): {$this->uri}"
      );
    }
    $this->uri = $uri;
    $this->realUri = $this->getRealUri($uri);
  }

  /**
   * {@inheritdoc}
   */
  public function getUri() {
    return $this->uri;
  }

  /**
   * Returns the real URI behind an XNTT URI.
   *
   * @param string $uri
   *   The XNTT URI to translate. If not set, use current URI.
   *
   * @return string
   *   The real URI corresponding to the given XNTT URI.
   */
  public function getRealUri($uri = NULL) {
    if (!isset($uri)) {
      // Not specified, use current URI.
      if (!empty($this->realUri)) {
        // Already computed.
        return $this->realUri;
      }
      $uri = $this->uri;
      $set_member = TRUE;
    }

    if (!empty($uri)
        && preg_match('#^' . static::XNTT_STREAM_REGEX . '$#', $uri, $matches)
    ) {
      // Load real URI behind XNTT URI.
      [, $xntt_type, $xntt_id, $file_field_name, $file_num, $file_name] =
        $matches;
      $external_file_storage = \Drupal::entityTypeManager()->getStorage('xnttfile');
      $real_uri = $external_file_storage->getRealUri(
        $xntt_type,
        $xntt_id,
        $file_field_name,
        $file_num
      );

      // If it is an HTTP URL, we need to encode it properly.
      if (preg_match('#^https?:#', $real_uri ?? '')) {
        $url_parts = parse_url($real_uri);

        // Encode path.
        if (isset($url_parts['path'])) {
          $path_parts = explode('/', $url_parts['path']);
          $encoded_path = implode(
            '/',
            array_map('rawurlencode', $path_parts)
          );
          $url_parts['path'] = ($path_parts[0] === '')
            ? '/' . ltrim($encoded_path, '/')
            : $encoded_path;
        }

        // Encode query.
        if (isset($url_parts['query'])) {
          parse_str($url_parts['query'], $query_params);
          $url_parts['query'] = http_build_query($query_params);
        }

        // Encode fragment.
        if (isset($url_parts['fragment'])) {
          $url_parts['fragment'] = rawurlencode($url_parts['fragment']);
        }

        // Rebuild URL.
        $encoded_url = $url_parts['scheme'] . '://';
        if (isset($url_parts['user'])) {
          $encoded_url .= $url_parts['user'];
          if (isset($url_parts['pass'])) {
              $encoded_url .= ':' . $url_parts['pass'];
          }
          $encoded_url .= '@';
        }
        if (isset($url_parts['host'])) {
          $encoded_url .= $url_parts['host'];
        }
        if (isset($url_parts['port'])) {
          $encoded_url .= ':' . $url_parts['port'];
        }
        if (isset($url_parts['path'])) {
          $encoded_url .= $url_parts['path'];
        }
        if (isset($url_parts['query'])) {
          $encoded_url .= '?' . $url_parts['query'];
        }
        if (isset($url_parts['fragment'])) {
          $encoded_url .= '#' . $url_parts['fragment'];
        }
        $real_uri = $encoded_url;
      }

      // Make sure we don't point back to an xntt file.
      if (!empty($real_uri) && $this->isXnttScheme($real_uri)) {
        $real_uri = '';
      }
    }

    $real_uri ??= '';

    // Store real URI if needed.
    if (!empty($set_member)) {
      $this->realUri = $real_uri;
    }

    return $real_uri;
  }

  /**
   * Gets the name of the directory from a given path.
   *
   * This method is usually accessed through drupal_dirname(), which wraps
   * around the PHP dirname() function because it does not support stream
   * wrappers.
   *
   * @param string $uri
   *   A URI or path.
   *
   * @return string
   *   A string containing the directory name.
   *
   * @see drupal_dirname()
   */
  public function dirname($uri = NULL) {
    if (!isset($uri)) {
      $uri = $this->getRealUri();
    }

    if (!$this->isXnttScheme($uri)
        && ($wrapper = \Drupal::service('stream_wrapper_manager')->getViaUri($uri))
    ) {
      // Use real URI stream wrapper.
      return $wrapper->dirname($uri);
    }

    $scheme = strstr($uri, '://', TRUE);
    if (!empty($scheme)) {
      $scheme .= '://';
    }
    else {
      $scheme = '/';
    }

    $dirname = dirname($this->getTarget($uri));
    if ('.' === $dirname) {
      $dirname = '';
    }

    return $scheme . $dirname;
  }

  /**
   * Returns the target of the resource within the stream.
   *
   * This function should be used in place of calls to realpath() or similar
   * functions when attempting to determine the location of a file. While
   * functions like realpath() may return the location of a read-only file, this
   * method may return a URI or path suitable for writing that is completely
   * separate from the URI used for reading.
   *
   * @param string $uri
   *   Optional URI.
   *
   * @return string
   *   Returns a string representing a location suitable for writing of a file.
   *
   * @throws \InvalidArgumentException
   *   If a malformed $uri parameter is passed in.
   */
  protected function getTarget($uri = NULL) {
    if (!isset($uri)) {
      $uri = $this->getRealUri();
    }

    if (!$this->isXnttScheme($uri)
        && ($wrapper = \Drupal::service('stream_wrapper_manager')->getViaUri($uri))
    ) {
      // Use real URI stream wrapper.
      return $wrapper->getTarget($uri);
    }

    $path = strstr($uri, '://');
    if (FALSE === $path) {
      // The delimiter '://' was not found in $uri, malformed $uri passed.
      throw new \InvalidArgumentException("Malformed uri parameter passed: $uri");
    }

    // Remove '://' and leading or trailing forward-slashes and backslashes.
    return trim(substr($path, 3), '\/');
  }

  /**
   * {@inheritdoc}
   */
  public function realpath() {
    return $this->getLocalPath();
  }

  /**
   * Returns the canonical absolute path of the URI, if possible.
   *
   * @param string $uri
   *   (optional) The stream wrapper URI to be converted to a canonical
   *   absolute path. This may point to a directory or another type of file.
   *
   * @return string|bool
   *   If $uri is not set, returns the canonical absolute path of the URI
   *   previously set by the
   *   Drupal\Core\StreamWrapper\StreamWrapperInterface::setUri() function.
   *   If $uri is set and valid for this class, returns its canonical absolute
   *   path, as determined by the realpath() function. If $uri is set but not
   *   valid, returns FALSE.
   */
  protected function getLocalPath($uri = NULL) {
    if (!isset($uri)) {
      $uri = $this->getRealUri();
    }

    if (!$this->isXnttScheme($uri)
        && ($wrapper = \Drupal::service('stream_wrapper_manager')->getViaUri($uri))
    ) {
      // Use real URI stream wrapper.
      return $wrapper->getLocalPath($uri);
    }

    // For other unsupported protcols.
    $path = $this->getDirectoryPath() . '/' . $this->getTarget($uri);
    return $path;
  }

  /**
   * Support for fopen(), file_get_contents(), etc.
   *
   * Any write modes will be rejected, as this is a read-only stream wrapper.
   *
   * @param string $uri
   *   A string containing the URI to the file to open.
   * @param int $mode
   *   The file mode, only strict readonly modes are supported.
   * @param int $options
   *   A bit mask of STREAM_USE_PATH and STREAM_REPORT_ERRORS.
   * @param string $opened_path
   *   A string containing the path actually opened.
   *
   * @return bool
   *   TRUE if $mode denotes a readonly mode and the file was opened
   *   successfully, FALSE otherwise.
   *
   * @see http://php.net/manual/streamwrapper.stream-open.php
   */
  public function stream_open($uri, $mode, $options, &$opened_path) {
    if (!in_array($mode, ['r', 'rb', 'rt'])) {
      if ($options & STREAM_REPORT_ERRORS) {
        trigger_error(
          'stream_open() write modes not supported for read-only stream wrappers',
          E_USER_WARNING
        );
      }
      return FALSE;
    }

    $this->uri = $uri;
    $this->realUri = $this->getRealUri($uri);
    $this->offset = 0;
    $this->data = NULL;

    if (!$this->isXnttScheme($this->realUri)
        && ($wrapper = \Drupal::service('stream_wrapper_manager')->getViaUri($this->realUri))
    ) {
      // Use real URI stream wrapper.
      return $wrapper->stream_open($this->realUri, $mode, $options, $opened_path);
    }

    $this->handle = ($options & STREAM_REPORT_ERRORS)
      ? fopen($this->realUri, $mode)
      : @fopen($this->realUri, $mode);

    if ((bool) $this->handle && ($options & STREAM_USE_PATH)) {
      $opened_path = $this->realUri;
    }

    return (bool) $this->handle;
  }

  /**
   * Support for flock().
   *
   * An exclusive lock attempt will be rejected, as this is a read-only stream
   * wrapper.
   *
   * @param int $operation
   *   One of the following:
   *   - LOCK_SH to acquire a shared lock (reader).
   *   - LOCK_EX to acquire an exclusive lock (writer).
   *   - LOCK_UN to release a lock (shared or exclusive).
   *   - LOCK_NB added as a bitmask if you don't want flock() to block while
   *     locking (not supported on Windows).
   *
   * @return bool
   *   Return FALSE for an exclusive lock (writer), as this is a read-only
   *   stream wrapper.  Return the result of flock() for other valid operations.
   *   Defaults to TRUE if an invalid operation is passed.
   *
   * @see http://php.net/manual/streamwrapper.stream-lock.php
   */
  public function stream_lock($operation) {
    // Disallow exclusive lock or non-blocking lock requests.
    if (in_array($operation, [LOCK_EX, LOCK_EX | LOCK_NB])) {
      trigger_error(
        'stream_lock() exclusive lock operations not supported for read-only stream wrappers',
        E_USER_WARNING
      );
      return FALSE;
    }
    if (in_array($operation, [LOCK_SH, LOCK_UN, LOCK_SH | LOCK_NB])) {
      if (!$this->isXnttScheme($this->realUri)
          && ($wrapper = \Drupal::service('stream_wrapper_manager')->getViaUri($this->realUri))
      ) {
        // Use real URI stream wrapper.
        return $wrapper->stream_lock($operation);
      }
      return flock($this->handle, $operation);
    }

    return TRUE;
  }

  /**
   * Support for fread(), file_get_contents() etc.
   *
   * @param int $count
   *   Maximum number of bytes to be read.
   *
   * @return string|bool
   *   The string that was read, or FALSE in case of an error.
   *
   * @see http://php.net/manual/streamwrapper.stream-read.php
   */
  public function stream_read($count) {
    if (!$this->isXnttScheme($this->realUri)
        && ($wrapper = \Drupal::service('stream_wrapper_manager')->getViaUri($this->realUri))
    ) {
      // Use real URI stream wrapper.
      return $wrapper->stream_read($count);
    }
    if (isset($this->data)) {
      $result = substr($this->data, $this->offset, $count);
      $this->offset += strlen($result);
      return $result;
    }
    return fread($this->handle, $count);
  }

  /**
   * Support for fwrite(), file_put_contents() etc.
   *
   * Data will not be written as this is a read-only stream wrapper.
   *
   * @param string $data
   *   The string to be written.
   *
   * @return bool
   *   FALSE as data will not be written.
   *
   * @see http://php.net/manual/en/streamwrapper.stream-write.php
   */
  public function stream_write($data) {
    trigger_error(
      'stream_write() not supported for read-only stream wrappers',
      E_USER_WARNING
    );
    return FALSE;
  }

  /**
   * Support for feof().
   *
   * @return bool
   *   TRUE if end-of-file has been reached.
   *
   * @see http://php.net/manual/streamwrapper.stream-eof.php
   */
  public function stream_eof() {
    if (!$this->isXnttScheme($this->realUri)
        && ($wrapper = \Drupal::service('stream_wrapper_manager')->getViaUri($this->realUri))
    ) {
      // Use real URI stream wrapper.
      return $wrapper->stream_eof();
    }
    if (isset($this->data)) {
      return $this->offset >= strlen($this->data);
    }
    return feof($this->handle);
  }

  /**
   * {@inheritdoc}
   *
   * This implementation of fseek() offers a partial support for HTTP streams.
   * PHP implementation of HTTP streams does not support fseek(). In fact, it
   * partially supports fseek() if it is a forward move and not more than a
   * certain buffer size, otherwise it returns -1.
   * When dealing with image files, Drupal image style features expects to be
   * able to move on will in the file. Since this module tries to mimic the
   * behavior of a local file with HTTP files, it would fails depending on the
   * fseek() request.
   * To avoid that, in case of a fseek() failure on an HTTP stream, this stream
   * wrapper reopen the remote file and get its full content in memory. Then, it
   * will emulate the stream operations on the file content in memory.
   * This might be a problem if the remote file is large (eg. video files).
   * Going a step further was considered: just loading in memory parts of the
   * file that are requested, but it would not work because we can't go as far
   * as we want in remote files using fseek(). We need to get the content to go
   * further and get the rest. So I stopped trying to implement a more
   * sofisticated approach that would just read chunks and store those in
   * memory (intervals of data to fetch and merge).
   * The problem remains: for large files, this approach will try to use as much
   * memory as possible and fail if there is not enough memory. Maybe using
   * temporary files could be considered instead if the problem is reported.
   */
  public function stream_seek($offset, $whence = SEEK_SET) {
    if (!$this->isXnttScheme($this->realUri)
        && ($wrapper = \Drupal::service('stream_wrapper_manager')->getViaUri($this->realUri))
    ) {
      // Use real URI stream wrapper.
      return $wrapper->stream_seek($offset, $whence);
    }
    // If we got file content in memory, use it.
    if (isset($this->data)) {
      $this->offset = match ($whence) {
        SEEK_SET => $offset,
        SEEK_END => strlen($this->data) + $offset,
        SEEK_CUR => $this->offset + $offset,
        default => $this->offset,
      };
      return TRUE;
    }
    // Function fseek returns 0 on success and -1 on a failure.
    // "stream_seek": 1 on success and  0 on a failure.
    $return = !fseek($this->handle, $offset, $whence);
    if (!$return
      && (!empty($this->realUri))
      && (str_starts_with($this->realUri, 'http'))
    ) {
      // "fseek()" failed, try to get the whole file to work in memory.
      // Estimate free memory first.
      $memory_remaining = NULL;
      $memory_limit = ini_get('memory_limit');
      if (($memory_limit > 0)
          && preg_match('/^(\d+\.?\d*)(\w?)$/', $memory_limit, $matches)
      ) {
        $multiplier = 1;
        switch ($matches[2]) {
          case 'k':
          case 'K':
            $multiplier = 1024;
            break;
          case 'm':
          case 'M':
            $multiplier = 1048576;
            break;
          case 'g':
          case 'G':
            $multiplier = 1073741824;
            break;
          default:
            break;
        }
        $memory_limit_bytes = $matches[1] * $multiplier;
        $memory_used = memory_get_usage(true);
        // Keep at least 32M free memory (arbitrary value).
        $memory_remaining = $memory_limit_bytes - $memory_used - 33554432;
        if (0 >= $memory_remaining) {
          // Not enough memory to get a file safely.
          trigger_error(
            'stream_seek(): not enough memory to process file over HTTP.',
            E_USER_WARNING
          );
          return FALSE;
        }
      }
      fclose($this->handle);
      // Read the whole file content.
      $this->handle = @fopen($this->realUri, $mode);
      // Check if we have memory limitations.
      if (isset($memory_remaining)) {
        // Limit reading to available memory.
        $this->data = stream_get_contents($this->handle, $memory_remaining);
        if ((FALSE === $this->data)
            || (strlen($this->data) >= $memory_remaining)
        ) {
          // We reached the limit. It means we may not have everything, stop.
          fclose($this->handle);
          $this->data = NULL;
          trigger_error(
            'stream_seek(): not enough memory to process file over HTTP (file too large).',
            E_USER_WARNING
          );
          return FALSE;
        }
      }
      else {
        // No memory limitation.
        $this->data = stream_get_contents($this->handle);
      }
      fclose($this->handle);
      $this->handle = NULL;
      $this->offset = match ($whence) {
        SEEK_SET => $offset,
        SEEK_END => strlen($this->data) + $offset,
        SEEK_CUR => $this->offset + $offset,
        default => $this->offset,
      };
      $return =
        !empty($this->data)
        && (0 <= $this->offset)
        && (strlen($this->data) >= $this->offset);
    }
    return $return;
  }

  /**
   * Support for fflush().
   *
   * Nothing will be output to the file, as this is a read-only stream wrapper.
   * However as stream_flush is called during stream_close we should not trigger
   * an error.
   *
   * @return bool
   *   FALSE, as no data will be stored.
   *
   * @see http://php.net/manual/streamwrapper.stream-flush.php
   */
  public function stream_flush() {
    return FALSE;
  }

  /**
   * Support for ftell().
   *
   * @return bool
   *   The current offset in bytes from the beginning of file.
   *
   * @see http://php.net/manual/streamwrapper.stream-tell.php
   */
  public function stream_tell() {
    if (!$this->isXnttScheme($this->realUri)
        && ($wrapper = \Drupal::service('stream_wrapper_manager')->getViaUri($this->realUri))
    ) {
      // Use real URI stream wrapper.
      return $wrapper->stream_tell();
    }
    if (isset($this->data)) {
      return $this->offset;
    }
    return ftell($this->handle);
  }

  /**
   * Support for fstat().
   *
   * @return bool
   *   An array with file status, or FALSE in case of an error - see fstat()
   *   for a description of this array.
   *
   * @see http://php.net/manual/streamwrapper.stream-stat.php
   */
  public function stream_stat() {
    if (!$this->isXnttScheme($this->realUri)
        && ($wrapper = \Drupal::service('stream_wrapper_manager')->getViaUri($this->realUri))
    ) {
      // Use real URI stream wrapper.
      return $wrapper->stream_stat();
    }
    if (isset($this->data)) {
      $current_time = time();
      $filesize = strlen($this->data);
      $stat = [
        0 => 0,
        1 => 0,
        2 => 0444,
        3 => 1,
        4 => 0,
        5 => 0,
        6 => 0,
        7 => $filesize,
        8 => $current_time,
        9 => $current_time,
        10 => $current_time,
        11 => -1,
        12 => -1,
        "dev" => 0,
        "ino" => 0,
        "mode" => 0444,
        "nlink" => 1,
        "uid" => 0,
        "gid" => 0,
        "rdev" => 0,
        "size" => $filesize,
        "atime" => $current_time,
        "mtime" => $current_time,
        "ctime" => $current_time,
        "blksize" => -1,
        "blocks" => -1,
      ];
      return $stat;
    }
    return fstat($this->handle);
  }

  /**
   * Support for fclose().
   *
   * @return bool
   *   TRUE if stream was successfully closed.
   *
   * @see http://php.net/manual/streamwrapper.stream-close.php
   */
  public function stream_close() {
    if (!$this->isXnttScheme($this->realUri)
        && ($wrapper = \Drupal::service('stream_wrapper_manager')->getViaUri($this->realUri))
    ) {
      // Use real URI stream wrapper.
      return $wrapper->stream_close();
    }
    $this->data = NULL;
    return $this->handle ? fclose($this->handle) : TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function stream_cast($cast_as) {
    if (!$this->isXnttScheme($this->realUri)
        && ($wrapper = \Drupal::service('stream_wrapper_manager')->getViaUri($this->realUri))
    ) {
      // Use real URI stream wrapper.
      return $wrapper->stream_cast($cast_as);
    }
    return $this->handle ? $this->handle : FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * Does not change meta data as this is a read-only stream wrapper.
   */
  public function stream_metadata($uri, $option, $value) {
    trigger_error(
      'stream_metadata() not supported for read-only stream wrappers',
      E_USER_WARNING
    );
    return FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * Since Windows systems do not allow it and it is not needed for most use
   * cases anyway, this method is not supported on local files and will trigger
   * an error and return false. If needed, custom subclasses can provide
   * OS-specific implementations for advanced use cases.
   */
  public function stream_set_option($option, $arg1, $arg2) {
    trigger_error(
      'stream_set_option() not supported for local file based stream wrappers',
      E_USER_WARNING
    );
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function stream_truncate($new_size) {
    trigger_error(
      'stream_truncate() not supported for read-only stream wrappers',
      E_USER_WARNING
    );
    return FALSE;
  }

  /**
   * Support for unlink().
   *
   * The file will not be deleted from the stream as this is a read-only stream
   * wrapper.
   *
   * @param string $uri
   *   A string containing the uri to the resource to delete.
   *
   * @return bool
   *   TRUE so that file_delete() will remove db reference to file. File is not
   *   actually deleted.
   *
   * @see http://php.net/manual/en/streamwrapper.unlink.php
   */
  public function unlink($uri) {
    trigger_error(
      'unlink() not supported for read-only stream wrappers',
      E_USER_WARNING
    );
    return TRUE;
  }

  /**
   * Support for rename().
   *
   * The file will not be renamed as this is a read-only stream wrapper.
   *
   * @param string $from_uri
   *   The uri to the file to rename.
   * @param string $to_uri
   *   The new uri for file.
   *
   * @return bool
   *   FALSE as file will never be renamed.
   *
   * @see http://php.net/manual/en/streamwrapper.rename.php
   */
  public function rename($from_uri, $to_uri) {
    trigger_error(
      'rename() not supported for read-only stream wrappers',
      E_USER_WARNING
    );
    return FALSE;
  }

  /**
   * Support for mkdir().
   *
   * Directory will never be created as this is a read-only stream wrapper.
   *
   * @param string $uri
   *   A string containing the URI to the directory to create.
   * @param int $mode
   *   Permission flags - see mkdir().
   * @param int $options
   *   A bit mask of STREAM_REPORT_ERRORS and STREAM_MKDIR_RECURSIVE.
   *
   * @return bool
   *   FALSE as directory will never be created.
   *
   * @see http://php.net/manual/en/streamwrapper.mkdir.php
   */
  public function mkdir($uri, $mode, $options) {
    trigger_error(
      'mkdir() not supported for read-only stream wrappers',
      E_USER_WARNING
    );
    return FALSE;
  }

  /**
   * Support for rmdir().
   *
   * Directory will never be deleted as this is a read-only stream wrapper.
   *
   * @param string $uri
   *   A string containing the URI to the directory to delete.
   * @param int $options
   *   A bit mask of STREAM_REPORT_ERRORS.
   *
   * @return bool
   *   FALSE as directory will never be deleted.
   *
   * @see http://php.net/manual/en/streamwrapper.rmdir.php
   */
  public function rmdir($uri, $options) {
    trigger_error(
      'rmdir() not supported for read-only stream wrappers',
      E_USER_WARNING
    );
    return FALSE;
  }

  /**
   * Support for stat().
   *
   * @param string $uri
   *   A string containing the URI to get information about.
   * @param int $flags
   *   A bit mask of STREAM_URL_STAT_LINK and STREAM_URL_STAT_QUIET.
   *
   * @return array
   *   An array with file status, or FALSE in case of an error - see fstat()
   *   for a description of this array.
   *
   * @see http://php.net/manual/streamwrapper.url-stat.php
   */
  public function url_stat($uri, $flags) {
    $this->uri = $uri;
    $this->realUri = $this->getRealUri($uri);

    if (!$this->isXnttScheme($this->realUri)
        && ($wrapper = \Drupal::service('stream_wrapper_manager')->getViaUri($this->realUri))
    ) {
      // Use real URI stream wrapper.
      return $wrapper->url_stat($this->realUri, $flags);
    }
    $current_time = time();
    // A non-0 value.
    $filesize = 512;
    // Get size for HTTP files.
    if ((0 === stripos($uri, 'http://')) || (0 === stripos($uri, 'https://'))) {
      $data = get_headers($this->realUri, TRUE);
      $filesize = isset($data['Content-Length']) ? (int) $data['Content-Length'] : 0;
    }

    $stat = [
      0 => 0,
      1 => 0,
      2 => 0444,
      3 => 1,
      4 => 0,
      5 => 0,
      6 => 0,
      7 => $filesize,
      8 => $current_time,
      9 => $current_time,
      10 => $current_time,
      11 => -1,
      12 => -1,
      "dev" => 0,
      "ino" => 0,
      "mode" => 0444,
      "nlink" => 1,
      "uid" => 0,
      "gid" => 0,
      "rdev" => 0,
      "size" => $filesize,
      "atime" => $current_time,
      "mtime" => $current_time,
      "ctime" => $current_time,
      "blksize" => -1,
      "blocks" => -1,
    ];
    return $stat;
  }

  /**
   * Support for opendir().
   *
   * @param string $uri
   *   A string containing the URI to the directory to open.
   * @param int $options
   *   Unknown (parameter is not documented in PHP Manual).
   *
   * @return bool
   *   TRUE on success.
   *
   * @see http://php.net/manual/streamwrapper.dir-opendir.php
   */
  public function dir_opendir($uri, $options) {
    // No support for external path.
    return FALSE;
  }

  /**
   * Support for readdir().
   *
   * @return string
   *   The next filename, or FALSE if there are no more files in the directory.
   *
   * @see http://php.net/manual/streamwrapper.dir-readdir.php
   */
  public function dir_readdir() {
    // No support for external path.
    return FALSE;
  }

  /**
   * Support for rewinddir().
   *
   * @return bool
   *   TRUE on success.
   *
   * @see http://php.net/manual/streamwrapper.dir-rewinddir.php
   */
  public function dir_rewinddir() {
    // No support for external path.
    return FALSE;
  }

  /**
   * Support for closedir().
   *
   * @return bool
   *   TRUE on success.
   *
   * @see http://php.net/manual/streamwrapper.dir-closedir.php
   */
  public function dir_closedir() {
    // No support for external path.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public static function getType() {
    // Return StreamWrapperInterface::LOCAL | StreamWrapperInterface::READ;.
    return StreamWrapperInterface::READ;
  }

  /**
   * {@inheritdoc}
   */
  public function getExternalUrl() {
    $real_uri = $this->getRealUri();

    if (!$this->isXnttScheme($real_uri)
        && ($wrapper = \Drupal::service('stream_wrapper_manager')->getViaUri($real_uri))
    ) {
      // Use real URI stream wrapper.
      return $wrapper->getExternalUrl();
    }

    // Check if it is a web file.
    if ((0 === strpos($real_uri, 'http://'))
        || (0 === strpos($real_uri, 'https://'))
    ) {
      return $real_uri;
    }

    return '';
  }

  /**
   * Returns the current request object.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   The current request object.
   */
  protected function getRequest() {
    if (!isset($this->request)) {
      $this->request = \Drupal::service('request_stack')->getCurrentRequest();
    }
    return $this->request;
  }

  /**
   * Gets the path that the wrapper is responsible for.
   *
   * @return string
   *   String specifying the path.
   */
  public function getDirectoryPath() {
    $real_uri = $this->getRealUri();

    if (!$this->isXnttScheme($real_uri)
        && ($wrapper = \Drupal::service('stream_wrapper_manager')->getViaUri($real_uri))
    ) {
      // Use real URI stream wrapper.
      return $wrapper->getDirectoryPath();
    }

    // Other protocols.
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return t('External files');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return t('External files stored anywhere.');
  }

}
