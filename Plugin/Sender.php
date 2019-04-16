<?php
/**
 * Created by PhpStorm.
 * User: M_A_i
 * Date: 1/29/2019
 * Time: 5:18 PM
 */

namespace Mageplaza\Smtp\Plugin;
use Mageplaza\Smtp\Mail\Rse\Mail;
use Magento\Framework\Mail\Template\SenderResolverInterface;
class Sender
{
    protected $resourceMail;
    protected $senderResolver;
    public function __construct(
        Mail $resourceMail,
        SenderResolverInterface $SenderResolver

    )
    {
        $this->resourceMail   = $resourceMail;
        $this->senderResolver = $SenderResolver;
    }

    public function beforeSetFrom(\Magento\Framework\Mail\Template\TransportBuilder $subject,$from){
        $result = $from;
        if(is_string($from)){
            $result = $this->senderResolver->resolve($from);
        }
        if(is_array($from)) {
            $result = $from;
        }
        $this->resourceMail->setFromByStore($result['email'], $result['name']);

        return [$from];

    }
}
