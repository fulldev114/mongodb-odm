<?php

declare(strict_types=1);

namespace Doctrine\ODM\MongoDB\Tests\Functional;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Doctrine\ODM\MongoDB\Tests\BaseTest;

class ReferenceDiscriminatorsTest extends BaseTest
{
    public function setUp()
    {
        parent::setUp();
        $this->dm->getSchemaManager()->ensureDocumentIndexes(CommentableAction::class);
        $this->dm->getSchemaManager()->ensureDocumentIndexes(GroupMainActivityStreamItem::class);
        $this->dm->getSchemaManager()->ensureDocumentIndexes(GroupMembersActivityStreamItem::class);
        $this->dm->getSchemaManager()->ensureDocumentIndexes(UserDashboardActivityStreamItem::class);
        $this->dm->getSchemaManager()->ensureDocumentIndexes(UserProfileActivityStreamItem::class);
    }

    /**
     * This test demonstrates a CommentableAction being published to activity streams.
     */
    public function testReferenceDiscriminators()
    {
        $this->dm->persist($commentableAction = new CommentableAction('actionType'));
        $this->dm->persist($groupMainActivityStreamItem = new GroupMainActivityStreamItem($commentableAction, 'groupId'));
        $this->dm->persist($groupMemberActivityStreamItem = new GroupMembersActivityStreamItem($commentableAction, 'groupId'));
        $this->dm->persist($userDashboardActivityStreamItem = new UserDashboardActivityStreamItem($commentableAction, 'userId'));
        $this->dm->persist($userProfileActivityStreamItem = new UserProfileActivityStreamItem($commentableAction, 'userId'));

        $this->dm->flush();
        $this->dm->clear();

        $commentableAction = $this->dm->find(CommentableAction::class, $commentableAction->getId());
        $groupMainActivityStreamItem = $this->dm->find(GroupMainActivityStreamItem::class, $groupMainActivityStreamItem->getId());
        $groupMemberActivityStreamItem = $this->dm->find(GroupMembersActivityStreamItem::class, $groupMemberActivityStreamItem->getId());
        $userDashboardActivityStreamItem = $this->dm->find(UserDashboardActivityStreamItem::class, $userDashboardActivityStreamItem->getId());
        $userProfileActivityStreamItem = $this->dm->find(UserProfileActivityStreamItem::class, $userProfileActivityStreamItem->getId());

        $this->assertSame($commentableAction, $groupMainActivityStreamItem->getAction());
        $this->assertSame($commentableAction, $groupMemberActivityStreamItem->getAction());
        $this->assertSame($commentableAction, $userDashboardActivityStreamItem->getAction());
        $this->assertSame($commentableAction, $userProfileActivityStreamItem->getAction());
    }

    /**
     * This tests demonstrates a race condition between two requests which are
     * both publishing a CommentableAction to activity streams.
     */
    public function testReferenceDiscriminatorsRaceCondition()
    {
        $this->dm->persist($commentableAction1 = new CommentableAction('actionType'));
        $this->dm->persist($groupMainActivityStreamItem1 = new GroupMainActivityStreamItem($commentableAction1, 'groupId'));
        $this->dm->persist($groupMemberActivityStreamItem1 = new GroupMembersActivityStreamItem($commentableAction1, 'groupId'));
        $this->dm->persist($userDashboardActivityStreamItem1 = new UserDashboardActivityStreamItem($commentableAction1, 'userId'));
        $this->dm->persist($userProfileActivityStreamItem1 = new UserProfileActivityStreamItem($commentableAction1, 'userId'));

        $this->dm->persist($commentableAction2 = new CommentableAction('actionType'));
        $this->dm->persist($groupMainActivityStreamItem2 = new GroupMainActivityStreamItem($commentableAction2, 'groupId'));
        $this->dm->persist($groupMemberActivityStreamItem2 = new GroupMembersActivityStreamItem($commentableAction2, 'groupId'));
        $this->dm->persist($userDashboardActivityStreamItem2 = new UserDashboardActivityStreamItem($commentableAction2, 'userId'));
        $this->dm->persist($userProfileActivityStreamItem2 = new UserProfileActivityStreamItem($commentableAction2, 'userId'));

        $this->dm->flush();
        $this->dm->clear();

        $commentableAction1 = $this->dm->find(CommentableAction::class, $commentableAction1->getId());
        $groupMainActivityStreamItem1 = $this->dm->find(GroupMainActivityStreamItem::class, $groupMainActivityStreamItem1->getId());
        $groupMemberActivityStreamItem1 = $this->dm->find(GroupMembersActivityStreamItem::class, $groupMemberActivityStreamItem1->getId());
        $userDashboardActivityStreamItem1 = $this->dm->find(UserDashboardActivityStreamItem::class, $userDashboardActivityStreamItem1->getId());
        $userProfileActivityStreamItem1 = $this->dm->find(UserProfileActivityStreamItem::class, $userProfileActivityStreamItem1->getId());

        $commentableAction2 = $this->dm->find(CommentableAction::class, $commentableAction2->getId());
        $groupMainActivityStreamItem2 = $this->dm->find(GroupMainActivityStreamItem::class, $groupMainActivityStreamItem2->getId());
        $groupMemberActivityStreamItem2 = $this->dm->find(GroupMembersActivityStreamItem::class, $groupMemberActivityStreamItem2->getId());
        $userDashboardActivityStreamItem2 = $this->dm->find(UserDashboardActivityStreamItem::class, $userDashboardActivityStreamItem2->getId());
        $userProfileActivityStreamItem2 = $this->dm->find(UserProfileActivityStreamItem::class, $userProfileActivityStreamItem2->getId());

        $this->assertSame($commentableAction1, $groupMainActivityStreamItem1->getAction());
        $this->assertSame($commentableAction1, $groupMemberActivityStreamItem1->getAction());
        $this->assertSame($commentableAction1, $userDashboardActivityStreamItem1->getAction());
        $this->assertSame($commentableAction1, $userProfileActivityStreamItem1->getAction());

        $this->assertSame($commentableAction2, $groupMainActivityStreamItem2->getAction());
        $this->assertSame($commentableAction2, $groupMemberActivityStreamItem2->getAction());
        $this->assertSame($commentableAction2, $userDashboardActivityStreamItem2->getAction());
        $this->assertSame($commentableAction2, $userProfileActivityStreamItem2->getAction());
    }
}

/**
 * @ODM\Document(collection="rdt_action")
 * @ODM\InheritanceType("SINGLE_COLLECTION")
 * @ODM\DiscriminatorField("discriminator")
 * @ODM\DiscriminatorMap({"action"=Action::class, "commentable_action"=CommentableAction::class})
 */
class Action
{
    /** @ODM\Id */
    protected $id;

    /** @ODM\Field(type="string") */
    protected $type;

    public function __construct($type)
    {
        $this->type = $type;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getType()
    {
        return $this->type;
    }
}

/** @ODM\Document */
class CommentableAction extends Action
{
    /** @ODM\Field(type="collection") **/
    protected $comments = [];

    public function __construct($type, array $comments = [])
    {
        parent::__construct($type);
        $this->comments = $comments;
    }

    public function getComments()
    {
        return $this->comments;
    }
}

/** @ODM\MappedSuperclass */
abstract class ActivityStreamItem
{
    /** @ODM\Id */
    protected $id;

    /** @ODM\ReferenceOne(targetDocument=Action::class) */
    protected $action;

    public function __construct(Action $action)
    {
        $this->action = $action;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getAction()
    {
        return $this->action;
    }
}

/**
 * @ODM\MappedSuperclass
 * @ODM\UniqueIndex(keys={"groupId"="asc", "action.$id"="asc"}, options={"unique"="true", "dropDups"="true"})
 */
abstract class GroupActivityStreamItem extends ActivityStreamItem
{
    /** @ODM\Field(type="string") */
    protected $groupId;

    public function __construct(Action $action, $groupId)
    {
        parent::__construct($action);
        $this->groupId = $groupId;
    }

    public function getGroupId()
    {
        return $this->groupId;
    }
}

/** @ODM\Document(collection="rdt_group_main_activity_stream_item") */
class GroupMainActivityStreamItem extends GroupActivityStreamItem
{
}

/** @ODM\Document(collection="rdt_group_members_activity_stream_item") */
class GroupMembersActivityStreamItem extends GroupActivityStreamItem
{
}

/**
 * @ODM\MappedSuperclass
 * @ODM\UniqueIndex(keys={"userId"="asc", "action.$id"="asc"}, options={"unique"="true", "dropDups"="true"})
 */
abstract class UserActivityStreamItem extends ActivityStreamItem
{
    /** @ODM\Field(type="string") */
    protected $userId;

    public function __construct(Action $action, $userId)
    {
        parent::__construct($action);
        $this->userId = $userId;
    }

    public function getUserId()
    {
        return $this->userId;
    }
}

/** @ODM\Document(collection="rdt_user_dashboard_activity_stream_item") */
class UserDashboardActivityStreamItem extends UserActivityStreamItem
{
}

/** @ODM\Document(collection="rdt_user_profile_activity_stream_item") */
class UserProfileActivityStreamItem extends UserActivityStreamItem
{
}
