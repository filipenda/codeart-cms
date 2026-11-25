<?php

namespace Codeart\Core\Modules;

/** module.json is missing, unreadable or breaks the rules; the message says which rule. */
class InvalidManifest extends ModuleException {}
