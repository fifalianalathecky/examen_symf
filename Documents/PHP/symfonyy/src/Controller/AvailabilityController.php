<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Form\ReservationType;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AvailabilityController extends AbstractController
{
    private $logger;
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $logger = null)
    {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    #[Route('/availability', name: 'app_availability')]
    public function index(Request $request, ReservationRepository $reservationRepo): Response
    {
        $reservation = new Reservation();
        $form = $this->createForm(ReservationType::class, $reservation);
        $form->handleRequest($request);

        $availableRooms = [];
        $isAvailable = false;
        $message = '';
        $hasSearched = $form->isSubmitted();
        $formError = null;

        // Log de débogage
        if ($this->logger) {
            $this->logger->info('Formulaire soumis: ' . ($hasSearched ? 'Oui' : 'Non'));
            if ($hasSearched) {
                $this->logger->info('Données du formulaire:', $request->request->all());
                $this->logger->info('Est valide: ' . ($form->isValid() ? 'Oui' : 'Non'));
            }
        }

        if ($form->isSubmitted()) {
            if ($this->logger) {
                $this->logger->info('Formulaire soumis, validation en cours...');
            }
            
            if ($form->isValid()) {
                if ($this->logger) {
                    $this->logger->info('Formulaire valide, traitement...');
                }
                try {
                    $startTime = $reservation->getStartTime();
                    $endTime = $reservation->getEndTime();
                    $date = $reservation->getDate();
                    $room = $reservation->getRoom();

                    // Vérifier si l'heure de fin est après l'heure de début
                    if ($endTime <= $startTime) {
                        $formError = 'L\'heure de fin doit être postérieure à l\'heure de début.';
                        $this->addFlash('error', $formError);
                        if ($this->logger) {
                            $this->logger->warning('Erreur de validation: heure de fin avant heure de début');
                        }
                    } else {
                        // Si une salle est spécifiée, vérifier sa disponibilité
                        if ($room) {
                            $isAvailable = $this->isRoomAvailable(
                                $reservationRepo,
                                $room,
                                $date,
                                $startTime,
                                $endTime
                            );

                            if ($isAvailable) {
                                if ($this->logger) {
                                    $this->logger->info('Salle disponible, redirection vers le formulaire de réservation', [
                                        'room' => $room,
                                        'date' => $date->format('Y-m-d'),
                                        'startTime' => $startTime->format('H:i'),
                                        'endTime' => $endTime->format('H:i')
                                    ]);
                                }
                                // Rediriger vers la page de réservation avec les paramètres pré-remplis
                                return $this->redirectToRoute('app_reservation_new', [
                                    'room' => $room,
                                    'date' => $date->format('Y-m-d'),
                                    'startTime' => $startTime->format('H:i'),
                                    'endTime' => $endTime->format('H:i')
                                ]);
                            } else {
                                $message = 'La salle n\'est pas disponible pour cette plage horaire.';
                                $this->addFlash('warning', $message);
                                if ($this->logger) {
                                    $this->logger->info('Salle non disponible', ['room' => $room]);
                                }
                            }
                        }

                        // Toujours chercher les salles disponibles
                        $availableRooms = $this->getAvailableRooms(
                            $reservationRepo,
                            $date,
                            $startTime,
                            $endTime,
                            $room // Exclure la salle déjà sélectionnée
                        );

                        if (empty($availableRooms) && !$room) {
                            $message = 'Aucune salle n\'est disponible pour cette plage horaire.';
                            $this->addFlash('info', $message);
                        }
                    }
                } catch (\Exception $e) {
                    $errorMsg = 'Une erreur est survenue lors de la vérification de la disponibilité.';
                    $this->addFlash('error', $errorMsg);
                    
                    // Log l'erreur si un logger est disponible
                    if ($this->logger) {
                        $this->logger->error('Erreur de disponibilité: ' . $e->getMessage(), [
                            'exception' => $e,
                            'trace' => $e->getTraceAsString()
                        ]);
                    }
                }
            } else {
                // Gérer les erreurs de validation du formulaire
                $formError = 'Veuillez corriger les erreurs dans le formulaire.';
                $this->addFlash('error', $formError);
            }
        }

        return $this->render('availability/index.html.twig', [
            'form' => $form->createView(),
            'isAvailable' => $isAvailable,
            'message' => $message,
            'availableRooms' => $availableRooms,
            'hasSearched' => $hasSearched,
            'formError' => $formError,
        ]);
    }

    private function isRoomAvailable(
        ReservationRepository $repo, 
        string $room, 
        \DateTimeInterface $date, 
        \DateTimeInterface $startTime, 
        \DateTimeInterface $endTime
    ): bool {
        try {
            $conflictingReservations = $repo->findConflictingReservations(
                $room,
                $date,
                $startTime,
                $endTime
            );

            return count($conflictingReservations) === 0;
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Erreur lors de la vérification de la salle: ' . $e->getMessage());
            }
            throw $e;
        }
    }

    private function getAvailableRooms(
        ReservationRepository $repo, 
        \DateTimeInterface $date, 
        \DateTimeInterface $startTime, 
        \DateTimeInterface $endTime,
        ?string $excludeRoom = null
    ): array {
        $allRooms = ['Salle 1', 'Salle 2', 'Salle 3', 'Salle de réunion', 'Salle de conférence'];
        $availableRooms = [];

        foreach ($allRooms as $room) {
            // Exclure la salle spécifiée si nécessaire
            if ($excludeRoom && $room === $excludeRoom) {
                continue;
            }

            try {
                $isAvailable = $this->isRoomAvailable($repo, $room, $date, $startTime, $endTime);
                if ($isAvailable) {
                    $availableRooms[] = $room;
                }
            } catch (\Exception $e) {
                // Continuer avec les autres salles en cas d'erreur sur une salle
                continue;
            }
        }

        return $availableRooms;
    }
}
