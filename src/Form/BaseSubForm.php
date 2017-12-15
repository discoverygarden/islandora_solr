<?php

namespace Drupal\islandora_solr\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\BaseFormIdInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Render\RendererInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The abstract base form.
 */
abstract class BaseSubForm extends FormBase implements BaseFormIdInterface {
  protected $type = NULL;

  /**
   * Date formatter instance.
   *
   * @var Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Renderer instance.
   *
   * @var Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  public function __construct(DateFormatterInterface $date_formatter, RendererInterface $renderer) {
    $this->dateFormatter = $date_formatter;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    if ($this->type === NULL) {
      throw new Exception('Attempting to get form ID before type is set.');
    }
    return static::BASE_ID . "_{$this->type}";
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseFormId() {
    return static::BASE_ID;
  }

  /**
   * {@inheritdoc}
   */
  public function create(ContainerInterface $container) {
    return new static(
      $container->get('date.formatter'),
      $container->get('renderer')
    );
  }

  /**
   * Set the subform index.
   *
   * @param string|int $type
   *   The request-unique subform index.
   */
  public function setType($type) {
    $this->type = $type;
  }

}
