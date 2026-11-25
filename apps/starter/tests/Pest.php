<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

// Module tests enable modules (DDL), which cannot run inside RefreshDatabase's transaction.
pest()->extend(TestCase::class)->in('Modules');
