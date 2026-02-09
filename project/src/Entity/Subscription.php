<?php

namespace App\Entity;

use App\Repository\SubscriptionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
class Subscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'subscriptions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?WeatherCache $location = null;

    #[ORM\Column(nullable: true)]
    private ?float $tempLowerBoundary = null;

    #[ORM\Column(nullable: true)]
    private ?float $tempUpperBoundary = null;

    #[ORM\Column]
    private ?bool $isLowerTriggered = null;

    #[ORM\Column]
    private ?bool $isUpperTriggered = null;

    #[ORM\Column]
    private ?bool $isActive = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @var Collection<int, Notification>
     */
    #[ORM\OneToMany(targetEntity: Notification::class, mappedBy: 'subscription', orphanRemoval: true)]
    private Collection $notifications;

    public function __construct()
    {
        $this->notifications = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->isLowerTriggered = false;
        $this->isUpperTriggered = false;
        $this->isActive = true;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getLocation(): ?WeatherCache
    {
        return $this->location;
    }

    public function setLocation(?WeatherCache $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getTempLowerBoundary(): ?float
    {
        return $this->tempLowerBoundary;
    }

    public function setTempLowerBoundary(?float $tempLowerBoundary): static
    {
        $this->tempLowerBoundary = $tempLowerBoundary;

        return $this;
    }

    public function getTempUpperBoundary(): ?float
    {
        return $this->tempUpperBoundary;
    }

    public function setTempUpperBoundary(?float $tempUpperBoundary): static
    {
        $this->tempUpperBoundary = $tempUpperBoundary;

        return $this;
    }

    public function isLowerTriggered(): ?bool
    {
        return $this->isLowerTriggered;
    }

    public function setIsLowerTriggered(bool $isLowerTriggered): static
    {
        $this->isLowerTriggered = $isLowerTriggered;

        return $this;
    }

    public function isUpperTriggered(): ?bool
    {
        return $this->isUpperTriggered;
    }

    public function setIsUpperTriggered(bool $isUpperTriggered): static
    {
        $this->isUpperTriggered = $isUpperTriggered;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @return Collection<int, Notification>
     */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    public function addNotification(Notification $notification): static
    {
        if (!$this->notifications->contains($notification)) {
            $this->notifications->add($notification);
            $notification->setSubscription($this);
        }

        return $this;
    }

    public function removeNotification(Notification $notification): static
    {
        if ($this->notifications->removeElement($notification)) {
            // set the owning side to null (unless already changed)
            if ($notification->getSubscription() === $this) {
                $notification->setSubscription(null);
            }
        }

        return $this;
    }
}
