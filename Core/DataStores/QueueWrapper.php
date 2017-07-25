<?php

namespace SmashPig\Core\DataStores;

use PHPQueue\Interfaces\FifoQueueStore;
use SmashPig\Core\Context;
use SmashPig\CrmLink\Messages\SourceFields;

class QueueWrapper {

    /**
     * @param string $queueName
     * @param array|JsonSerializableObject $message
     */
    public static function push( $queueName, $message ) {
        if ( $message instanceof JsonSerializableObject ) {
            $message = json_decode( $message->toJson(), true );
        }
        $queue = self::getQueue( $queueName );
        SourceFields::addToMessage( $message );
        $queue->push( $message );
    }

    /**
     * @param string $queueName
     * @return FifoQueueStore
     */
    public static function getQueue( $queueName ) {
        $config = Context::get()->getGlobalConfiguration();
        $key = "data-store/$queueName";

        // Examine the config node for a queue name
        $node = $config->val( $key );
        if (
            empty( $node['constructor-parameters'] ) ||
            empty( $node['constructor-parameters'][0]['queue'] )
        ) {
            $nameParam = array(
                'data-store' => array(
                    $queueName => array(
                        'constructor-parameters' => array(
                            array(
                                'queue' => $queueName
                            )
                        )
                    )
                )
            );
            $config->override( $nameParam );
        }

        return $config->object( $key );
    }

}
