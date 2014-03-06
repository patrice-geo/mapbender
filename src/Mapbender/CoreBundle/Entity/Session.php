<?php

namespace Mapbender\CoreBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Mapbender\CoreBundle\Component\Utils;

/**
 * Source entity
 *
 * @author Paul Schmidt
 *
 * @ORM\Entity
 * @ORM\Table(name="session")
 */
class Session
{
    /**
     * @var string $session_id;
     * @ORM\Id
     * @ORM\Column(type="string", nullable=false)
     */
    protected $session_id;
    
    /**
     * @var string $session_value The session value
     * @ORM\Column(type="text", nullable=false)
     */
    protected $session_value;
    
    /**
     * @var string $session_value The session timestamp
     * @ORM\Column(type="integer", nullable=false)
     */
    protected $session_time;
}
