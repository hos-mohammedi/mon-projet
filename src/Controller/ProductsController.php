<?php

namespace App\Controller;

use App\Entity\Products;
use App\Form\ProductsType;
use App\Repository\ProductsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class ProductsController extends AbstractController
{

    public function __construct(private EntityManagerInterface $manager)
    {
    }

    #[Route('/products', name: 'app_products')]
    public function index(ProductsRepository $productsRepository): Response
    {
        $products = $productsRepository->findAll();

        return $this->render('products/index.html.twig', [
            'products' => $products,
        ]);
    }

    #[Route('/products/add', name: 'app_products.add')]
    public function add(Request $request, SluggerInterface $slugger): Response
    {
        $produit = new Products();

        $form = $this->createForm(ProductsType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $imageFile = $form->get('image')->getData();

            // this condition is needed because the 'brochure' field is not required
            // so the PDF file must be processed only when a file is uploaded
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);

                // this is needed to safely include the file name as part of the URL
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
                // Move the file to the directory where brochures are stored
                try {
                    $imageFile->move(
                        $this->getParameter('images_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    // ... handle exception if something happens during file upload
                    $e->getMessage();
                }

                // updates the 'brochureFilename' property to store the PDF file name
                // instead of its contents
                $produit->setImage($newFilename);
            }

            $this->manager->persist($produit);
            $this->manager->flush();
            $this->addFlash('success', 'Le produit a été ajouté avec succès');
            return $this->redirectToRoute('app_products');
        }

        return $this->render('products/add.html.twig', [
            'form' => $form->createView(),
            'controller_name' => 'ProductsController',
        ]);
    }

    #[Route('/products/edit/{id}', name: 'app_products.edit')]
    public function edit(
        Request $request,
        SluggerInterface $slugger,
        Products $produit
    ): Response {

        $form = $this->createForm(ProductsType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $imageFile = $form->get('image')->getData();

            // this condition is needed because the 'brochure' field is not required
            // so the PDF file must be processed only when a file is uploaded
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);

                // this is needed to safely include the file name as part of the URL
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
                // Move the file to the directory where brochures are stored

                if (file_exists($this->getParameter('images_directory') . '/' . $produit->getImage())) {
                    unlink($this->getParameter('images_directory') . '/' . $produit->getImage());
                }

                try {
                    $imageFile->move(
                        $this->getParameter('images_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    // ... handle exception if something happens during file upload
                    $e->getMessage();
                }

                // updates the 'brochureFilename' property to store the PDF file name
                // instead of its contents
                $produit->setImage($newFilename);
            }

            $this->manager->persist($produit);
            $this->manager->flush();
            $this->addFlash('success', 'Le produit a été modifié avec succès');
            return $this->redirectToRoute('app_products');
        }

        return $this->render('products/edit.html.twig', [
            'form' => $form->createView(),
            'product' => $produit,
        ]);
    }

    #[Route('/products/show/{id}', name: 'app_products.show')]
    public function show(ProductsRepository $productsRepository, int $id): Response
    {
        $product = $productsRepository->find($id);

        return $this->render('products/show.html.twig', [
            'product' => $product,
        ]);
    }

    #[Route('/products/delete/{id}', name: 'app_products.delete')]
    public function delete(Request $request, Products $product): Response
    {

        // 'delete-item' is the same value used in the template to generate the token
        if ($this->isCsrfTokenValid('delete' . $product->getId(), $request->get('_token'))) {
            $this->manager->remove($product);
            unlink($this->getParameter('images_directory') . '/' . $product->getImage());
            $this->manager->flush();
            $this->addFlash('success', 'Le produit a été supprimé avec suucès');
        }
        return $this->redirectToRoute('app_products');
    }
}
