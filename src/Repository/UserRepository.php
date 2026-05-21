<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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

    public function findOneByNormalizedUsername(string $username): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('LOWER(u.username) = :username')
            ->setParameter('username', strtolower(trim($username)))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return User[]
     */
    public function findForAdminList(?string $search, ?string $role, ?string $status, int $limit, int $offset): array
    {
        $qb = $this->createQueryBuilder('u')
            ->orderBy('u.id', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ($search !== null && trim($search) !== '') {
            $qb->andWhere('LOWER(u.username) LIKE :search OR LOWER(COALESCE(u.name, \'\')) LIKE :search')
                ->setParameter('search', '%'.strtolower(trim($search)).'%');
        }

        if ($role !== null && trim($role) !== '') {
            $qb->andWhere('u.roles LIKE :role')
                ->setParameter('role', '%'.strtoupper(trim($role)).'%');
        }

        if ($status !== null && trim($status) !== '') {
            $qb->andWhere('u.status = :status')
                ->setParameter('status', strtolower(trim($status)));
        }

        return $qb->getQuery()->getResult();
    }

    private function createStaffMembersQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.roles LIKE :staffRole OR u.roles LIKE :adminRole')
            ->setParameter('staffRole', '%"ROLE_STAFF"%')
            ->setParameter('adminRole', '%"ROLE_ADMIN"%');
    }

    /**
     * @return User[]
     */
    public function findStaffMembersOrderedByNewest(): array
    {
        return $this->createStaffMembersQueryBuilder()
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
