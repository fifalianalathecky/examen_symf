<?php

namespace App\Repository;

use App\Entity\Reservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    /**
     * Trouve les réservations en conflit avec une plage horaire donnée
     */
    public function findConflictingReservations(
        string $room,
        \DateTimeInterface $date,
        \DateTimeInterface $startTime,
        \DateTimeInterface $endTime,
        ?int $excludeReservationId = null
    ): array {
        $qb = $this->createQueryBuilder('r')
            ->where('r.room = :room')
            ->andWhere('r.date = :date')
            ->andWhere('(
                (r.startTime < :endTime AND r.endTime > :startTime)
            )')
            ->setParameter('room', $room)
            ->setParameter('date', $date)
            ->setParameter('startTime', $startTime)
            ->setParameter('endTime', $endTime);

        if ($excludeReservationId !== null) {
            $qb->andWhere('r.id != :id')
               ->setParameter('id', $excludeReservationId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les réservations à venir pour une salle donnée
     */
    public function findUpcomingReservations(string $room, \DateTimeInterface $fromDate = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->where('r.room = :room')
            ->setParameter('room', $room)
            ->orderBy('r.date', 'ASC')
            ->addOrderBy('r.startTime', 'ASC');

        if ($fromDate) {
            $qb->andWhere('r.date >= :fromDate')
               ->setParameter('fromDate', $fromDate);
        }

        return $qb->getQuery()->getResult();
    }
}
