<?php

namespace App\Entity;

use App\Repository\SkillRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SkillRepository::class)]
class Skill
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column]
    private ?int $baseDmg = null;

    #[ORM\Column(length: 50)]
    private ?string $dmgType = null;

    #[ORM\OneToMany(mappedBy: 'skill', targetEntity: SkillTable::class)]
    private Collection $skill_table;

    #[ORM\ManyToMany(mappedBy: 'skill', targetEntity: DemonPlayer::class)]
    private Collection $demonPlayers;

    public function __construct()
    {
        $this->demonPlayers = new ArrayCollection();
        $this->skill_table = new ArrayCollection();
    }


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getBaseDmg(): ?int
    {
        return $this->baseDmg;
    }

    public function setBaseDmg(int $baseDmg): static
    {
        $this->baseDmg = $baseDmg;

        return $this;
    }


    public function getDmgType(): ?string
    {
        return $this->dmgType;
    }

    public function setDmgType(string $dmgType): static
    {
        $this->dmgType = $dmgType;

        return $this;
    }

    /**
     * @return Collection<int, DemonPlayer>
     */
    public function getDemonPlayers(): Collection
    {
        return $this->demonPlayers;
    }

    public function addDemonPlayer(DemonPlayer $demonPlayer): static
    {
        if (!$this->demonPlayers->contains($demonPlayer)) {
            $this->demonPlayers->add($demonPlayer);
            $demonPlayer->addSkill($this);
        }

        return $this;
    }

    public function removeDemonPlayer(DemonPlayer $demonPlayer): static
    {
        if ($this->demonPlayers->removeElement($demonPlayer)) {
            $demonPlayer->removeSkill($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, SkillTable>
     */
    public function getSkillTable(): Collection
    {
        return $this->skill_table;
    }

    public function addSkillTable(SkillTable $skillTable): static
    {
        if (!$this->skill_table->contains($skillTable)) {
            $this->skill_table->add($skillTable);
            $skillTable->setSkill($this);
        }

        return $this;
    }

    public function removeSkillTable(SkillTable $skillTable): static
    {
        if ($this->skill_table->removeElement($skillTable)) {
            // set the owning side to null (unless already changed)
            if ($skillTable->getSkill() === $this) {
                $skillTable->setSkill(null);
            }
        }

        return $this;
    }

}
