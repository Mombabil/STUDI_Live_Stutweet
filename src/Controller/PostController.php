<?php

namespace App\Controller;

use App\Entity\Post;
use App\Form\PostType;
use App\Repository\PostRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class PostController extends AbstractController
{
    // READ
    #[Route('/', name: "home")]
    public function index(ManagerRegistry $doctrine, Request $request, PostRepository $repository): Response
    {
        // pour la barre de recherche
        $search = $request->request->get("search");
        // equivalent de $_POST['search']

        $repository = $doctrine->getRepository(Post::class);
        $posts = $repository->findAll(); // SELECT * FROM 'post';

        // si on fait une recherche dans la barre, on affiche plus que le resultat de la recherche
        if ($search) {
            $posts = $repository->findBySearch($search);
            // SELECT * FROM posts WHERE title LIKE $search
            dump($posts);
        }

        return $this->render('post/index.html.twig', [
            "posts" => $posts,
        ]);
    }

    // CREATE
    #[Route('/post/new')]
    public function create(Request $request, ManagerRegistry $doctrine, SluggerInterface $slugger): Response
    {
        // si l'utilisateur n'est pas connecté, redirige vers login
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $post = new Post();

        // on crée le formulaire et a partir de l'objet Post()
        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);


        if ($form->isSubmitted() && $form->isValid()) {

            // on gere l'upload de l'image
            // pensez a modifier les parameters dans service.yaml
            /** @var UploadedFile $image */
            $image = $form->get('image')->getData();
            if ($image) {
                $originalFileName = pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFileName = $slugger->slug($originalFileName);
                $newFileName = $safeFileName . '-' . uniqid() . '-' . $image->guessExtension();

                try {
                    $image->move(
                        $this->getParameter('uploads'),
                        $newFileName
                    );
                } catch (FileException $e) {
                    dump($e);
                }

                $post->setImage($newFileName);
            }

            // on associe le post crée a l'utilisateur connecté
            $post->setUser($this->getUser());
            // on insère la date de publication
            $post->setPublishedAt(new \DateTime());
            // on crée une instance entityManager
            $entityManager = $doctrine->getManager();
            // on ajoute l'objet a l'entityManager
            $entityManager->persist($post);
            // on push l'objet dans la bdd
            $entityManager->flush();

            // on redirige l'utilisateur vers la page d'accueil
            return $this->redirectToRoute('home');
        }
        return $this->render('post/form.html.twig', [
            // on affiche la vue du formulaire
            'post_form' => $form->createView(),
            // protection du formulaire
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'post_item',
        ]);
    }

    // UPDATE
    #[Route('/post/edit/{id<\d+>}', name: "edit-post")]
    public function update(Request $request, Post $post, ManagerRegistry $doctrine): Response
    {
        // si l'utilisateur n'est pas connecté, redirige vers login
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        // on ne peut pas acceder au modification d'une publication d'un autre autheur, on redirige 
        if ($this->getUser() !== $post->getUser()) {
            // on affiche un message flash d'erreur
            $this->addFlash(
                "error",
                "Vous ne pouvez pas intervenir sur une publication qui ne vous appartient pas"
            );
            // on redirige l'utilisateur vers la page d'accueil
            return $this->redirectToRoute('home');
        }

        // on crée le formulaire et a partir de l'objet Post() que l'on veut modifier, sur la route /post/edit/{id}
        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);


        if ($form->isSubmitted() && $form->isValid()) {
            // on crée une instance entityManager
            $entityManager = $doctrine->getManager();

            // pas besoin de persist() ici car l'objet est deja dans l'entityManager

            // on modifie l'objet dans la bdd
            $entityManager->flush();

            // on redirige l'utilisateur vers la page d'accueil
            return $this->redirectToRoute('home');
        }
        return $this->render('post/form.html.twig', [
            // on affiche la vue du formulaire
            'post_form' => $form->createView(),
            // protection du formulaire
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'post_item',
        ]);
    }

    // DELETE
    #[Route('/post/delete/{id<\d+>}', name: "delete-post")]
    public function delete(Post $post, ManagerRegistry $doctrine): Response
    {
        // si l'utilisateur n'est pas connecté, redirige vers login
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        // on ne peut pas acceder au modification d'une publication d'un autre autheur, on redirige 
        if ($this->getUser() !== $post->getUser()) {
            // on affiche un message flash d'erreur
            $this->addFlash(
                "error",
                "Vous ne pouvez pas intervenir sur une publication qui ne vous appartient pas"
            );
            // on redirige l'utilisateur vers la page d'accueil
            return $this->redirectToRoute('home');
        }

        // on crée une instance entityManager
        $entityManager = $doctrine->getManager();
        // on ajoute l'objet a l'entityManager
        $entityManager->remove($post);
        // on supprime l'objet de la bdd (via son id)
        $entityManager->flush();

        // on redirige l'utilisateur vers la page d'accueil
        return $this->redirectToRoute('home');
    }

    // DUPLICATE
    #[Route('/post/copy/{id<\d+>}', name: "copy-post")]
    public function duplicate(Post $post, ManagerRegistry $doctrine): Response
    {
        // si l'utilisateur n'est pas connecté, redirige vers login
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        // on ne peut pas acceder au modification d'une publication d'un autre autheur, on redirige 
        if ($this->getUser() !== $post->getUser()) {
            // on affiche un message flash d'erreur
            $this->addFlash(
                "error",
                "Vous ne pouvez pas intervenir sur une publication qui ne vous appartient pas"
            );
            // on redirige l'utilisateur vers la page d'accueil
            return $this->redirectToRoute('home');
        }

        // on hydrate le nouveau post en clonant celui ciblé
        $copyPost = clone $post;
        // on crée une instance entityManager
        $entityManager = $doctrine->getManager();
        // on ajoute l'objet cloné a l'entityManager
        $entityManager->persist($copyPost);
        // on push l'objet cloné dans la bdd
        $entityManager->flush();

        // on redirige l'utilisateur vers la page d'accueil
        return $this->redirectToRoute('home');
    }

    // // SEARCH
    // #[Route('/post/search/{search}', name: "search-post")]
    // public function search(string $search): Response
    // {
    //     dump($search);

    //     return new Response("");
    //     // // on redirige l'utilisateur vers la page d'accueil
    //     // return $this->redirectToRoute('home');
    // }
}
