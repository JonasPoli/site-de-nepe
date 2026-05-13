<?php

namespace App\Form;

use App\Entity\ItemAdicional;
use App\Entity\TipoInscricao;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ItemAdicionalType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('descricao', TextType::class, ['label' => 'Descrição'])
            ->add('valor', MoneyType::class, [
                'label'    => 'Valor',
                'currency' => 'BRL',
                'divisor'  => 1,
            ])
            ->add('status', ChoiceType::class, [
                'label'   => 'Status',
                'choices' => ['Ativo' => 'ativo', 'Inativo' => 'inativo'],
            ])
            ->add('tipoInscricao', EntityType::class, [
                'class'        => TipoInscricao::class,
                'choice_label' => fn(TipoInscricao $t) => $t->getEvento()?->getNome() . ' — ' . $t->getNome(),
                'label'        => 'Tipo de Inscrição',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ItemAdicional::class,
        ]);
    }
}
