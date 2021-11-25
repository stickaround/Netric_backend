<?php
namespace NetricApi;

/**
 * Autogenerated by Thrift Compiler (0.14.2)
 *
 * DO NOT EDIT UNLESS YOU ARE SURE THAT YOU KNOW WHAT YOU ARE DOING
 *  @generated
 */
use Thrift\Base\TBase;
use Thrift\Type\TType;
use Thrift\Type\TMessageType;
use Thrift\Exception\TException;
use Thrift\Exception\TProtocolException;
use Thrift\Protocol\TProtocol;
use Thrift\Protocol\TBinaryProtocolAccelerated;
use Thrift\Exception\TApplicationException;

/**
 * Chat service used for chat-specific operations
 */
interface ChatIf
{
    /**
     * Notify any members who were not in a room when a new message is sent
     * 
     * @param string $messageId
     * @param string $accountId
     */
    public function notifyAbsentOfNewMessage($messageId, $accountId);
}
