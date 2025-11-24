<?php
/**
 * Events - Constants
 *
 * Constantes usadas em todo o módulo Events.
 *
 * @package    EauSystem
 * @subpackage Events\Config
 * @since      1.28.0
 */

namespace EauSystem\Events\Config;

if (!defined('WPINC')) {
    die;
}

/** @var string Post Type slug */
const POST_TYPE = 'eau_event';

/** @var string Prefixo dos meta fields */
const META_PREFIX = 'evt_';

/** @var string Taxonomy slug */
const TAXONOMY = 'cpd_category';

/** @var string Timezone padrão */
const DEFAULT_TIMEZONE = 'Australia/Sydney';

/** @var string País padrão */
const DEFAULT_COUNTRY = 'Australia';

/** @var string Visibilidade padrão */
const DEFAULT_VISIBILITY = 'public';

/** @var string Tipo de evento padrão */
const DEFAULT_EVENT_TYPE = 'in-person';
