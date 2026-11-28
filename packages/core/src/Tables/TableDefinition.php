<?php

namespace Codeart\Core\Tables;

/**
 * A table a module or page defines once. Registered under a short key in `TableRegistry`, so the bulk and export
 * endpoints (and the queued bulk job) can rebuild it from the key alone, never from user input.
 */
interface TableDefinition
{
    public function table(): DataTable;
}
