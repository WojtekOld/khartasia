<?php

namespace Drupal\external_entities\GuzzleHttp;

use Psr\Log\LoggerInterface;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

/**
 * Debug version of Guzzle Client that logs query info in Drupal logs.
 */
class DebugClient extends Client {

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Debug level of current instance.
   *
   * @var int
   */
  protected $debugLevel = 1;

  /**
   * {@inheritdoc}
   */
  public function request($method, $uri = '', array $options = []): ResponseInterface {
    if (2 <= $this->getDebugLevel() && $this->logger) {
      $this->logger->debug(
        "METHOD: '$method'\nURI: '$uri'\nOPTIONS:\n{options}",
        ['options' => print_r($options, TRUE)]
      );
    }
    $response = parent::request($method, $uri, $options);
    $status = $response->getStatusCode();
    if (2 <= $this->getDebugLevel() && $this->logger) {
      $this->logger->debug(
        "STATUS CODE: "
        . $status
        . "\nHEADERS: {headers}",
        ['headers' => print_r($response->getHeaders(), TRUE)]
      );
      if (200 == $status) {
        $this->logger->debug(
          "BODY:\n{body}",
          ['body' => '' . $response->getBody()]
        );
      }
    }
    elseif (1 == $this->getDebugLevel() && $this->logger) {
      $this->logger->debug(
        "METHOD: '$method'\nURI: '$uri'\nSTATUS CODE: $status"
      );
    }

    return $response;
  }

  /**
   * Returns debug level for current instance.
   *
   * @return int
   *   The debug level. 0 means debugging is off.
   */
  public function getDebugLevel() :int {
    return $this->debugLevel ?? 0;
  }

  /**
   * Sets debug level for current instance.
   *
   * @param int $debug_level
   *   Sets the debug level. A non-0 value means debugging is enabled while 0
   *   disables  debugging. Default to 1 (enabled).
   */
  public function setDebugLevel(int $debug_level = 1) {
    $this->debugLevel = $debug_level;
  }

  /**
   * Sets the logger to use for logs.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger to use.
   */
  public function setLogger(LoggerInterface $logger) {
    $this->logger = $logger;
  }

}
