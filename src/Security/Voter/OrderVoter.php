<?php

namespace App\Security\Voter;

use App\Entity\Order;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class OrderVoter extends Voter
{
    public const DELETE = 'ORDER_DELETE';
    public const EDIT = 'ORDER_EDIT';

    protected function supports(string $attribute, $subject): bool
    {
        return in_array($attribute, [self::DELETE, self::EDIT])
            && $subject instanceof Order;
    }

    protected function voteOnAttribute(string $attribute, $order, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        // ✅ Admin can edit/delete anything
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return true;
        }

        // // ✅ Staff can edit/delete only their own orders
        // return $order->getCreatedBy() === $user;
        // Staff rules
        if (in_array('ROLE_STAFF', $user->getRoles())) {

            if ($attribute === self::EDIT) {
                return true; // Staff can edit all products
            }

            if ($attribute === self::DELETE) {
                return false; // Staff cannot delete any product
            }
        }

        return false;
    }
}

