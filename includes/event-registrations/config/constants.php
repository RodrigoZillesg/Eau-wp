<?php
/**
 * Event Registrations - Constants
 *
 * Constantes usadas em todo o módulo Event Registrations.
 *
 * @package    EauSystem
 * @subpackage Events\Registrations\Config
 * @since      1.29.0
 */

namespace EauSystem\EventRegistrations\Config;

if (!defined('WPINC')) {
    die;
}

/** @var string Post Type slug */
const POST_TYPE = 'eau_event_reg';

/** @var string Prefixo dos meta fields */
const META_PREFIX = 'reg_';

/** @var string Status padrão */
const DEFAULT_STATUS = 'pending';
