<?php

namespace App\Form;

use App\Entity\Reservation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;

class ReservationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('room', ChoiceType::class, [
                'label' => 'Salle',
                'choices' => [
                    'Salle 1' => 'Salle 1',
                    'Salle 2' => 'Salle 2',
                    'Salle 3' => 'Salle 3',
                    'Salle de réunion' => 'Salle de réunion',
                    'Salle de conférence' => 'Salle de conférence',
                ],
                'placeholder' => 'Choisissez une salle',
                'required' => true,
            ])
            ->add('date', DateType::class, [
                'label' => 'Date de réservation',
                'widget' => 'single_text',
                'html5' => true,
                'attr' => ['min' => (new \DateTime())->format('Y-m-d')],
                'required' => true,
            ])
            ->add('startTime', TimeType::class, [
                'label' => 'Heure de début',
                'input' => 'datetime',
                'widget' => 'choice',
                'hours' => range(8, 20),
                'minutes' => [0, 15, 30, 45],
                'required' => true,
            ])
            ->add('endTime', TimeType::class, [
                'label' => 'Heure de fin',
                'input' => 'datetime',
                'widget' => 'choice',
                'hours' => range(8, 21),
                'minutes' => [0, 15, 30, 45],
                'required' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Reservation::class,
        ]);
    }
}
