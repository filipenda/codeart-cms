<?php

namespace Codeart\Core\Modules;

use RuntimeException;

/** Any module problem the operator should read (missing dependency, unknown module ...). */
class ModuleException extends RuntimeException {}
