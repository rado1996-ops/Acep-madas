<?php

namespace MailPoet\Doctrine\EntityTraits;

if (!defined('ABSPATH')) exit;


use DateTimeInterface;
use MailPoetVendor\Doctrine\ORM\Mapping as ORM;

trait DeletedAtTrait {
  /**
   * @ORM\Column(type="datetimetz", nullable=true)
   * @var DateTimeInterface|null
   */
  private $deleted_at;

  /** @return DateTimeInterface|null */
  function getDeletedAt() {
    return $this->deleted_at;
  }

  /** @param DateTimeInterface|null $deleted_at */
  function setDeletedAt($deleted_at) {
    $this->deleted_at = $deleted_at;
  }
}
