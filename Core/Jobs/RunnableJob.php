<?php namespace SmashPig\Core\Jobs;

use \SmashPig\Core\DataStores\KeyedOpaqueStorableObject;

abstract class RunnableJob extends KeyedOpaqueStorableObject {
	abstract public function execute();
}
