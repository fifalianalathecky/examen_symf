<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Form\ReservationType;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/reservation')]
class ReservationController extends AbstractController
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository
    ) {
    }

    #[Route('', name: 'app_reservation_index', methods: ['GET'])]
    public function index(): Response
    {
        $reservations = $this->reservationRepository->findBy(
            [],
            ['date' => 'DESC', 'startTime' => 'ASC']
        );

        return $this->render('reservation/index.html.twig', [
            'reservations' => $reservations,
        ]);
    }

    #[Route('/new', name: 'app_reservation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $reservation = new Reservation();
        
        // Pré-remplir le formulaire avec les paramètres de l'URL si présents
        if ($request->query->has('room') && $request->query->has('date') && 
            $request->query->has('startTime') && $request->query->has('endTime')) {
            
            $reservation->setRoom($request->query->get('room'));
            $reservation->setDate(\DateTime::createFromFormat('Y-m-d', $request->query->get('date')));
            $reservation->setStartTime(\DateTime::createFromFormat('H:i', $request->query->get('startTime')));
            $reservation->setEndTime(\DateTime::createFromFormat('H:i', $request->query->get('endTime')));
        }
        
        $form = $this->createForm(ReservationType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                // Vérifier que l'heure de fin est après l'heure de début
                if ($reservation->getEndTime() <= $reservation->getStartTime()) {
                    $this->addFlash('error', 'L\'heure de fin doit être postérieure à l\'heure de début.');
                    return $this->redirectToRoute('app_reservation_new', $request->query->all());
                }

                // Vérifier la disponibilité avant de sauvegarder
                $conflicts = $this->reservationRepository->findConflictingReservations(
                    $reservation->getRoom(),
                    $reservation->getDate(),
                    $reservation->getStartTime(),
                    $reservation->getEndTime()
                );

                if (count($conflicts) > 0) {
                    $this->addFlash('error', 'Cette salle n\'est plus disponible pour la plage horaire sélectionnée.');
                    return $this->redirectToRoute('app_availability', [
                        'room' => $reservation->getRoom(),
                        'date' => $reservation->getDate()->format('Y-m-d'),
                        'startTime' => $reservation->getStartTime()->format('H:i'),
                        'endTime' => $reservation->getEndTime()->format('H:i')
                    ]);
                }

                try {
                    $entityManager->persist($reservation);
                    $entityManager->flush();

                    $this->addFlash('success', 'La réservation a été enregistrée avec succès !');
                    return $this->redirectToRoute('app_reservation_index', [], Response::HTTP_SEE_OTHER);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Une erreur est survenue lors de l\'enregistrement de la réservation.');
                    if ($this->getParameter('kernel.environment') === 'dev') {
                        $this->addFlash('error', $e->getMessage());
                    }
                }
            } else {
                // Afficher les erreurs de validation
                foreach ($form->getErrors(true) as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
            }
        }

        return $this->render('reservation/new.html.twig', [
            'reservation' => $reservation,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_reservation_show', methods: ['GET'])]
    public function show(Reservation $reservation): Response
    {
        return $this->render('reservation/show.html.twig', [
            'reservation' => $reservation,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_reservation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Reservation $reservation, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ReservationType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Vérifier la disponibilité avant de sauvegarder
            $conflicts = $this->reservationRepository->findConflictingReservations(
                $reservation->getRoom(),
                $reservation->getDate(),
                $reservation->getStartTime(),
                $reservation->getEndTime(),
                $reservation->getId() // Exclure la réservation actuelle des conflits
            );

            if (count($conflicts) > 0) {
                $this->addFlash('error', 'Cette salle n\'est pas disponible pour la plage horaire sélectionnée.');
                return $this->redirectToRoute('app_reservation_edit', ['id' => $reservation->getId()]);
            }

            $entityManager->flush();
            $this->addFlash('success', 'La réservation a été mise à jour avec succès !');
            return $this->redirectToRoute('app_reservation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('reservation/edit.html.twig', [
            'reservation' => $reservation,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_reservation_delete', methods: ['POST'])]
    public function delete(Request $request, Reservation $reservation, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$reservation->getId(), $request->request->get('_token'))) {
            $entityManager->remove($reservation);
            $entityManager->flush();
            $this->addFlash('success', 'La réservation a été supprimée avec succès.');
        }

        return $this->redirectToRoute('app_reservation_index', [], Response::HTTP_SEE_OTHER);
    }
}
