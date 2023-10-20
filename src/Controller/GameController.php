<?php

namespace App\Controller;

use App\Entity\Skill;
use App\Entity\Battle;
use App\Entity\DemonBase;
use App\Entity\DemonTrait;
use App\Entity\DemonPlayer;
use App\Repository\SkillRepository;
use App\Repository\BattleRepository;
use App\Repository\PlayerRepository;
use App\Repository\DemonBaseRepository;
use App\Repository\DemonTraitRepository;
use App\Repository\SkillTableRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\DemonPlayerRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Csrf\TokenStorage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;


class GameController extends AbstractController
{

    // #[Route('/get-mp3-data', name: 'get_mp3_data')]
    // public function getMp3Data(): Response
    // {
    //     $mp3FilePath = realpath($this->getParameter('kernel.project_dir') . '/public/sfx/typewriter.mp3');
    //     $data = ['mp3FilePath' => $mp3FilePath];
    //     return new JsonResponse($data);
    // }


    #[Route('/endpoint', name: 'endpoint')]
    public function checkSignal(Request $request) {
        $output = $request->request->get('A');
        $session = $request->getSession();
        $session->set('placeholder','a');
        return new Response($output);
    }

    #[Route('/test/sessionReset', name: 'sessionReset')]
    public function reset(Request $request) {
        $session = $request->getSession();
        $session->clear();
        return $this->redirectToRoute("app_home");
    }

    #[Route('/game', name: 'game')]
    public function index(): Response
    {
        if ($this->isGranted("ROLE_IN_COMBAT")) return $this->redirectToRoute("combat");

        if ($this->getUser()->getStage() == 0)
        {
            return $this->render('game/index.html.twig', [
                'controller_name' => 'GameController',
            ]);           
        }
        else if ($this->getUser()->getStage() == 1)
        {
            $demonchoice = $this->getUser()->getDemonPlayer();
            foreach ($demonchoice as $demon)
            {
                $demon = $demon;
            }
            return $this->render('game/stageOne.html.twig', [
                'demon' => $demon,
            ]);    
        }
        else
        {

        }
    }

    #[Route('/ajaxe/combatAjax', name: 'combatAjax')]
    public function combatAjax(Request $request, ?Battle $battle, 
    ?DemonBaseRepository $demonBaseRepository, ?SkillTableRepository $skillRepository ,
    ?DemonTraitRepository $demonTraitRepository, PlayerRepository $playerRepository, 
    ?BattleRepository $battleRepository, EntityManagerInterface $entityManager, TokenStorageInterface $tokenStorage): Response
{
    // This is an AJAX request
    // Prepare your data here. This could be an object, an array, etc.
    // For example, let's use the same data you were passing to the Twig template:
    $idPLayer = $this->getUser()->getDemonPlayer();
    $idPLayer = $idPLayer[0];
    $battleContent = $battleRepository->findOneBy(["demonPlayer1" => $idPLayer]);
    $playerDemons = $this->getUser()->getDemonPlayer();
    $playerDemon = $playerDemons[0];
    $generatedCpu = $battleContent->getDemonPlayer2();
    if ($playerDemon->getTotalAgi() > $generatedCpu->getTotalAgi())
    {
        $initiative = $this->getUser()->getUsername();
    }
    else if($playerDemon->getTotalAgi() < $generatedCpu->getTotalAgi())
    {
        $initiative = 'CPU';
    }
    else
    {
    }
    // Prepare the data to return as JSON
    $data = [
        'cpuDemon' => [
            'id' => $generatedCpu->getId(),
            'hpMax' =>$generatedCpu->getMaxHp(),
            'name' => $generatedCpu->getDemonBase()->getName(),
            'skills' => array_map(function ($skill) 
            {
                return $skill->getName(); // adjust this based on your Skill entity structure
            }, $generatedCpu->getSkills()->toArray())
            
            // add other fields as needed
        ],
        'playerDemons' => array_map(function($demon) {
            return [
                'id' => $demon->getId(),
                'hpMax' =>$demon->getMaxHp(),
                'name' => $demon->getDemonBase()->getName(),
                'skills' => array_map(function ($skill) {
                    return $skill->getName(); // adjust this based on your Skill entity structure
                }, $demon->getSkills()->toArray())
                // add other fields as needed
            ];
        }, $playerDemons->toArray()),
        'initiative' => [
            'initiative' => $initiative
        ],
        'playersNames' => [
            'player1' => $this->getUser()->getUsername(),
            'player2' => 'CPU'
        ]
    ];
    
    // Return the data as a JSON response
    return new JsonResponse($data);
    
}

    #[Route('/game/ajaxe/SkillUsed', name: 'SkillUsedAjax')]
    public function skillUsed(Request $request, SkillRepository $skillRepository, DemonPlayerRepository $demonPlayerRepository, PlayerRepository $playerRepository): Response
    {
        $currentCPUHp = $request->request->get('hpCurrentCPU');
        $skillUsed = $request->request->get('skill');
        $demonPlayerId = $request->request->get('demonPlayer1Id');
        $cpuDemonId = $request->request->get('demonPlayer2Id');
        // $demonPlayerId = 118;
        // $cpuDemonId = 119;
        $skillObj = $skillRepository->findOneBy(["name" => $skillUsed]);
        $demonPlayerObj = $demonPlayerRepository->findOneBy(["id" => $demonPlayerId]);
        $cpuDemonObj = $demonPlayerRepository->findOneBy(["id" => $cpuDemonId]);
        $dmgDone = $skillObj->dmgCalc($demonPlayerObj, $cpuDemonObj);

        // $dmgDone = 1;
        $data = 
        [
            'dmg' => $dmgDone,
            'skillObj' => $skillObj,
            'demon1' => $demonPlayerId,
            'demon2' => $cpuDemonId,
            'skillused' => $skillUsed
        ];

        return new JsonResponse($data);
    }


    #[Route('/game/combat', name: 'combat')]
    public function combat(Request $request, ?Battle $battle, 
    ?DemonBaseRepository $demonBaseRepository, ?SkillTableRepository $skillRepository ,
    ?DemonTraitRepository $demonTraitRepository, PlayerRepository $playerRepository, 
    ?BattleRepository $battleRepository, EntityManagerInterface $entityManager, TokenStorageInterface $tokenStorage): Response
    {
        $session = $request->getSession();
        if ($session->get('placeholder') == 'a' && !$this->isGranted('ROLE_IN_COMBAT')) //Condition to start a new combat
        {
            $cpu = $playerRepository->findOneBy(["username" => "CPU"]);
            $battle = new Battle;
            $playerDemons = $this->getUser()->getDemonPlayer();
            $generatedCpu = $this->cpuDemonGen('imp', $demonBaseRepository, $skillRepository , $demonTraitRepository,$playerRepository, $entityManager);
            // $playerDemons[0]->addFighter($battle);
            // $generatedCpu->addFighter2($battle);
            $playerDemon = $playerDemons[0];
            $playerDemon->addFighter($battle);
            $generatedCpu->addFighter2($battle);
            $entityManager->persist($battle);
            $this->getUser()->addRole("ROLE_IN_COMBAT");
            // $token = new UsernamePasswordToken($this->getUser(), null, 'main', $this->getUser()->getRoles());
            // $this->get('security.token_storage')->setToken($token);
            $entityManager->persist($this->getUser());
            $entityManager->flush();
            if ($playerDemon->getTotalAgi() > $generatedCpu->getTotalAgi())
            {
                $initiative = $this->getUser()->getUsername();
            }
            else if($playerDemon->getTotalAgi() < $generatedCpu->getTotalAgi())
            {
                $initiative = 'CPU';
            }
            else
            {
                $initiative = rand($initiative = $this->getUser()->getUsername() , 'CPU');
            }
            return $this->render('game/combat.html.twig', [
                'cpuDemon' => $generatedCpu,
                'playerDemons' => $playerDemons,
                'intiative' => $initiative
            ]);    
        }
        else if ($this->isGranted('ROLE_IN_COMBAT')) //combat is still in progress so the user is put in it 
        {
            $idPLayer = $this->getUser()->getDemonPlayer();
            $idPLayer = $idPLayer[0];
            $battleContent = $battleRepository->findOneBy(["demonPlayer1" => $idPLayer]);
            $playerDemons = $this->getUser()->getDemonPlayer();
            $playerDemon = $playerDemons[0];
            $generatedCpu = $battleContent->getDemonPlayer2();
            if ($playerDemon->getTotalAgi() > $generatedCpu->getTotalAgi())
            {
                $initiative = $this->getUser()->getUsername();
            }
            else if($playerDemon->getTotalAgi() < $generatedCpu->getTotalAgi())
            {
                $initiative = 'CPU';
            }
            else
            {
            }
            return $this->render('game/combat.html.twig', [
                'cpuDemon' => $generatedCpu,
                'playerDemons' => $playerDemons,
                'initiative' => 'initiative'
            ]);    
        }

        return $this->redirectToRoute("app_home");
    }


    #[Route('/game/choice/{name}', name: 'choice', requirements : ['name' =>  '\w+'])]
    public function choiceHorus(string $name, SkillRepository $skillRepository, SkillTableRepository $skillTableRepository,
    DemonBaseRepository $demonBaseRepository, DemonTraitRepository $demonTraitRepository,
    EntityManagerInterface $entityManager): Response
    {
        if ($this->getUser()->getStage() == 0 && $name == 'Horus')
        {
            switch($name)
            {
                case "Horus":
                    $demonBase = $this->pickDemonBase($demonBaseRepository, 'horus');
                    $playerDemonTrait = $this->traitGen($demonTraitRepository);
                    $skills = $skillTableRepository->findBy(["level" => 1, "demonBase" => $demonBase->getId()],["id" => "ASC"]);
                    $skill = $skills[0]; //level on will only have on skill
                    $skill = $skill->getSkill();
                    break;

                case "Xiuhcoatl":
                    $demonBase = $this->pickDemonBase($demonBaseRepository, 'Xiuhcoatl');
                    $playerDemonTrait = $this->traitGen($demonTraitRepository);
                    $skills = $skillTableRepository->findBy(["level" => 1, "demonBase" => $demonBase->getId()],["id" => "ASC"]);
                    $skill = $skills[0]; //level on will only have on skill
                    $skill = $skill->getSkill();
                    break;
                
                case "Chernobog":
                    $demonBase = $this->pickDemonBase($demonBaseRepository, 'horus');
                    $playerDemonTrait = $this->traitGen($demonTraitRepository);
                    $skills = $skillTableRepository->findBy(["level" => 1, "demonBase" => $demonBase->getId()],["id" => "ASC"]);
                    $skill = $skills[0]; //level on will only have on skill
                    $skill = $skill->getSkill();
                    break;
            }

            $demonPlayer = new DemonPlayer; //create a demon
            $demonPlayer->setDemonBase($demonBase); //set base template
            $demonPlayer->setTrait($playerDemonTrait); //generate a trait
            $demonPlayer->addSkill($skill);
            $this->getUser()->addDemonPlayer($demonPlayer);
            $this->getUser()->setStage(1);
            $entityManager->persist($demonPlayer);
            $entityManager->flush();
            return $this->redirectToRoute("game");
        }   
        else
        {
            return $this->redirectToRoute('app_home');
        }
    }

    public function traitGen(DemonTraitRepository $demonTraitRepository) : DemonTrait
    {
        $traits = $demonTraitRepository->findBy([], ["name" => "ASC"]); 
        $pickedTrait = array_rand($traits);
        return $traits[$pickedTrait];
    }

    public function pickDemonBase(DemonBaseRepository $demonBaseRepository, string $demonName) : DemonBase
    {
        $demon = $demonBaseRepository->findOneBy(["name" => $demonName]);
        return $demon;
    }

    public function pickOneSkill(SkillRepository $skillRepo, string $skillName) : Skill
    {
        $skill = $skillRepository->findOneBy(["name" => $skillName]);
        return $skill;
    }

    public function pickAllLearnableSkills(SkillTableRepository $skillTableRepository, int $demonId)
    {
        $skills = $skillTableRepository->findBy(["demonBase" => $demonId], ["id" => "ASC"]);
        return $skills;
    }

    
    public function cpuDemonGen(string $string, ?DemonBaseRepository $demonBaseRepository, ?SkillTableRepository $skillRepo ,?DemonTraitRepository $demonTraitRepository, ?PlayerRepository $playerRepository, ?EntityManagerInterface $entityManager) : DemonPlayer
    {
        $trait = $this->traitGen($demonTraitRepository);
        $imp = $this->pickDemonBase($demonBaseRepository, 'imp');
        $cpu = $playerRepository->findOneBy(["username" => "CPU"]);
        $skillsTable = $this->pickAllLearnableSkills($skillRepo, $imp->getId());
        if (count($skillsTable) > 6)
        {
            $randSetOfSkills = array_rand($skills, 6);
            $skills = $skills[$randSetOfSkills];
        }
        $demonPlayer = new DemonPlayer; //create a demon
        $demonPlayer->setDemonBase($imp); //set base template
        $demonPlayer->setTrait($trait); //generate a trait
        foreach ($skillsTable as $skillTable) //it gives the id in the skill table but so we need to getskill
        {
            $skill = $skillTable->getSkill();
            $demonPlayer->addSkill($skill);
        }
        $cpu->addDemonPlayer($demonPlayer);

        $entityManager->persist($demonPlayer);
        $entityManager->flush();
        return $demonPlayer;
    }

    // public function inBattleCheck(Request $request, PlayerRepository $playerRepository, BattleRepository $battleRepository)
    // {
    //     $session = $request->getSession();
    //     $firstDemonPlayer = $this->getUser()->getDemonPlayer();
    //     $firstDemonPlayer = $firstDemonPlayer[0]->getId();
    //     $inBattle = $battleRepository->findBy(["demonPlayer1" => $firstDemonPlayer]);
    //     return $inBattle;
    // }

    /**
     * Use this method to refresh token roles immediately ||By PixelShaped https://github.com/symfony/symfony/issues/39763#issuecomment-925903934
     */
    // public function refreshToken($user, TokenStorageInterface $tokenStorage): void
    // {
    //     $token = new UsernamePasswordToken(
    //         $user,
    //         null,
    //         'main', // your firewall name
    //         $user->getRoles()
    //     );
    //     $tokenStorage->setToken($token);
    // }

}
