<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use League\OAuth2\Client\Provider\Facebook;
use League\OAuth2\Client\Provider\Google;
use Doctrine\ORM\EntityManagerInterface;


class HomeController extends AbstractController
{
   private $provider;

    public function __construct()
   {
       $this->provider = new Facebook([
       'clientId'           => $_ENV['FCB_ID'],
        'clientSecret'      => $_ENV['FCB_SECRET'],
        'redirectUri'       => $_ENV['FCB_CALLBACK'],
        'graphApiVersion'   => 'v19.0',
    ]);
   }




    #[Route('/', name: 'app_index_front')]
    public function index(UserRepository $userRepo): Response
    {
        $user = $this->getUser();
        //dd($userRepo->find($user)->isBlocked());
        
        
        
        //dd($this->getUser()->getRoles()[0]);
        if ($user){
            $isBlocked = $userRepo->find($user)->isBlocked();
            if ($user->getRoles()[0]=="ROLE_USER")
                if ($isBlocked)
                    return $this->redirectToRoute('app_login');
                else
                    return $this->render('home/index.html.twig', [
                ]);
            else if ($user->getRoles()[0]=="ROLE_ADMIN"){
                if ($isBlocked)
                    return $this->render('security/login.html.twig', ['isBlocked'=>$isBlocked]);
                else
                return $this->render('home/index_admin.html.twig', [
                ]);
            }
        }
        else
        return $this->render('base.html.twig', [
                ]);


    }
     #[Route('/admin', name: 'app_index_admin')]
    public function index_admin(): Response
    {
        return $this->render('home/index_admin.html.twig', [
            'controller_name' => 'HomeController',
        ]);
    }

    #[Route('/fcb-login', name: 'fcb_login')]
    public function fcbLogin(): Response

    {
         
        $helper_url=$this->provider->getAuthorizationUrl();

        return $this->redirect($helper_url);
    }



     #[Route('/fcb-callback', name: 'fcb_callback')]
    public function fcbCallBack(UserRepository $userDb, EntityManagerInterface $manager): Response
    {
       //Récupérer le token
        $token = $this->provider->getAccessToken('authorization_code', [
        'code' => $_GET['code']
        ]);

       try {    
           //Récupérer les informations de l'utilisateur

           $user=$this->provider->getResourceOwner($token);

           $user=$user->toArray();

           $email=$user['email'];

           $nom=$user['name'];

           $picture=array($user['picture_url']);

           //Vérifier si l'utilisateur existe dans la base des données

           $user_exist=$userDb->findOneByEmail($email);

           if($user_exist)
           {
                $user_exist->setNom($nom)
                         ->setPictureUrl($picture);

                $manager->flush();


                return $this->render('show/show.html.twig', [
                    'nom'=>$nom,
                    'picture'=>$picture[0]
                ]);


           }

           else
           {
                $new_user=new User();

                $new_user->setUsername($nom)
                      ->setEmail($email)
                      ->setPassword(sha1(str_shuffle('abscdop123390hHHH;:::OOOI')));
              
                $manager->persist($new_user);

                $manager->flush();


                return $this->render('show/show.html.twig', [
                    'nom'=>$nom,
                    'picture'=>$picture[0]
                ]);


           }


       } catch (\Throwable $th) {
        //throw $th;

          return $th->getMessage();
       }


    }

     #[Route('/user', name: 'app_user_admin', methods: ['GET'])]
     public function indexadmin(UserRepository $UserRepository): Response
    {
        return $this->render('user/index.html.twig', [
            'users' => $UserRepository->findAll(),
        ]);
    }

    #[Route('/user/{id}', name: 'app_ban_user', methods: ['GET'])]
    public function banUser(User $user,EntityManagerInterface $entitymanager): Response
    {
        //dd($user);
        $user->setBlocked(1);
        $entitymanager->persist($user);
        $entitymanager->flush();
        return $this->redirectToRoute('app_user_admin', [], Response::HTTP_SEE_OTHER);

    }
    // #[Route('/user/{id}', name: 'app_bann')]
    // public function changeUserRole($id, EntityManager  $entityManager){
    //     $em = $this->manager;
    //     $user = $this->repo->find($id);
    //     $user->setRoles(['ROLE_BANNED']);
    //     $em->persist($user);
    //     $em->flush();
    //     return $this->redirectToRoute('app_listusers');
    // }
}