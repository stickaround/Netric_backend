<?php
namespace Netric\Mail\Transport;

use Netric\ServiceManager\AccountServiceManagerInterface;
use Netric\ServiceManager\AccountServiceFactoryInterface;
use Netric\Config\ConfigFactory;
use Netric\Mail\Transport\BulkSmtpFactory;

/**
 * Create a new Transport service based on account settings for bulk email
 */
class BulkTransportFactory implements AccountServiceFactoryInterface
{
    /**
     * Service creation factory
     *
     * @param AccountServiceManagerInterface $serviceManager ServiceLocator for injecting dependencies
     * @return TransportInterface
     */
    public function createService(AccountServiceManagerInterface $serviceManager)
    {
        // Get the required method
        $config = $serviceManager->get(ConfigFactory::class);
        $transportMode = $config->email['mode'];

        // Create transport variable to set
        $transport = null;

        /*
         * If email is being suppressed via a config param, then return InMemory transport
         * so we do not try to send out emails in a development/test environment.
         */
        if (isset($config->email['supress'])) {
            return new InMemory();
        }

        // Call the factory to return simple transports
        switch ($transportMode) {
            case 'smtp':
                return $serviceManager->get(BulkSmtpFactory::class);
            case 'in-memory':
                return new InMemory();
            case 'sendmail':
                return new Sendmail();
        }

        throw new Exception\InvalidArgumentException("No transport for method " . $transportMode);
    }
}
