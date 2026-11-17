<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Usuários com o NEPE já carregado, prontos para a listagem agrupada do super admin:
     * por NEPE em ordem alfabética, depois por nome, com os sem nome no fim do grupo e
     * os sem NEPE no fim de tudo.
     *
     * O join evita uma consulta por linha só para mostrar o nome do NEPE.
     *
     * @return list<User>
     */
    public function findAllForSuperAdmin(?string $busca = null): array
    {
        $qb = $this->createQueryBuilder('u')
            ->addSelect('t')
            ->leftJoin('u.tenant', 't')
            // HIDDEN: entra só na ordenação, não no resultado
            ->addSelect('CASE WHEN u.tenant IS NULL THEN 1 ELSE 0 END AS HIDDEN semNepe')
            ->addSelect("CASE WHEN u.name = '' THEN 1 ELSE 0 END AS HIDDEN semNome")
            ->addOrderBy('semNepe', 'ASC')
            ->addOrderBy('t.name', 'ASC')
            ->addOrderBy('semNome', 'ASC')
            ->addOrderBy('u.name', 'ASC');

        if ($busca !== null && $busca !== '') {
            $qb->andWhere('u.name LIKE :busca OR u.username LIKE :busca OR u.email LIKE :busca')
                ->setParameter('busca', '%' . $busca . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

//    /**
//     * @return User[] Returns an array of User objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('u.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?User
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
