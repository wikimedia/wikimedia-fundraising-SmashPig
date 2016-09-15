<?php namespace SmashPig\Core\Jobs;

use SmashPig\Core\DataStores\KeyedOpaqueStorableObject;
use SmashPig\Core\Runnable;

/**
 * @deprecated New code should implement SmashPig\Core\Runnable instead.
 */
abstract class RunnableJob
	extends KeyedOpaqueStorableObject
	implements Runnable
{
	abstract public function execute();
}
