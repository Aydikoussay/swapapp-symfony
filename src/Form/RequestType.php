<?php

namespace App\Form;

use App\Entity\Article;
use App\Entity\Request;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
class RequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder




            ->add('message', TextareaType::class, [
                'required' => false,
                'label' => 'Leave a message  for the owner ? ',
                'attr' => ['rows' => 5, 'cols' => 50],
            ])

        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Request::class,
        ]);
    }
}
