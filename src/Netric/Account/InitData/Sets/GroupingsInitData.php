<?php

declare(strict_types=1);

namespace Netric\Account\InitData\Sets;

use Netric\Account\Account;
use Netric\Account\InitData\InitDataInterface;
use Netric\EntityGroupings\GroupingLoader;
use Netric\EntityGroupings\Group;

/**
 * Initializer to make sure accounts have a default set of groupings
 */
class GroupingsInitData implements InitDataInterface
{
    /**
     * Grouping data to save
     */
    private array $groupingData;

    /**
     * Load and save groupings
     */
    private GroupingLoader $groupingLoader;

    /**
     * Constructor
     *
     * @param array $groupingData Array of data to use for initializing groupings
     * @param GroupingLoader $groupingLoader
     */
    public function __construct(
        array $groupingData,
        GroupingLoader $groupingLoader
    ) {
        $this->groupingData = $groupingData;
        $this->groupingLoader = $groupingLoader;
    }

    /**
     * Insert or update initial data for account
     *
     * @param Account $account
     * @return bool
     */
    public function setInitialData(Account $account): bool
    {
        foreach ($this->groupingData as $objType => $fields) {
            foreach ($fields as $fieldName => $groupsData) {
                // Get groupings for each objType and $fieldName
                $groupings = $this->groupingLoader->get("$objType/$fieldName", $account->getAccountId());

                // TODO: We are going to add any groups that might be missing below
                // and see if that creates any problems for now.
                // Only create default groupings if none exist
                // if (count($groupings->getAll()) > 0) {
                //     continue;
                // }

                // Loop through each group and add
                foreach ($groupsData as $inxd => $groupData) {
                    if (!$groupings->getByName($groupData['name'])) {
                        $group = new Group();

                        // Required data
                        $group->name = $groupData['name'];

                        if (isset($groupData['sort_oder'])) {
                            $group->sortOrder = $groupData['sort_oder'];
                        } else {
                            $group->sortOrder = $inxd;
                        }

                        $groupings->add($group);
                    }
                }

                // Save changes to groupings
                $this->groupingLoader->save($groupings);
            }
        }

        return true;
    }
}
