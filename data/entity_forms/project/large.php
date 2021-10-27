<row>
    <column>
        <row>
            <column>
                <field name='name' hidelabel="true" class='headline'></field>
            </column>
        </row>
        <row>
            <column>
                <field name='notes' multiline='true'></field>
                <all_additional></all_additional>
            </column>
        </row>
        <row>
            <tabs>
                <tab name='Task'>
                    <objectsref obj_type='task' ref_field='project' view_id='all_incomplete_tasks'></objectsref>
                </tab>

                <tab name='Discussions'>
                    <objectsref obj_type='discussion'></objectsref>
                </tab>

                <tab name='Milestones'>
                    <objectsref obj_type='project_milestone' ref_field='project_id'></objectsref>
                </tab>

                <tab name='Files'>
                    <attachments></attachments>
                </tab>
                <tab name='Members'>
                    <members name='Members' field='members'></members>
                </tab>
            </tabs>
        </row>
    </column>
    <column type="sidebar">
        <header>Details</header>
        <row>
            <column>
                <field name='image_id' profile_image='t'></field>
            </column>
        </row>
        <row>
            <column>
                <field name='priority'></field>
            </column>
        </row>
        <row>
            <column>
                <field name='parent' tooltip='A project can be a child of much larger projects which allows for smaller teams working on massive projects. This is not a commonly used feature, few projects are of that scale; but if you find a project has too much noise from all the people and activity it might be helpful to split out subprojects and make smaller teams.'></field>
            </column>
        </row>
        <row>
            <column>
                <field name='owner_id' tooltip='Each project must have one responisble owner even though many members may be working on the project.'></field>
            </column>
        </row>
        <row>
            <column>
                <field name='customer_id'></field>
            </column>
        </row>
        <row>
            <column>
                <field name='date_started'></field>
            </column>
        </row>
        <row>
            <column>
                <field name='date_deadline' tooltip='If no deadline is set, this will be considered an ongoing project.'></field>
            </column>
        </row>
        <row>
            <column>
                <field name='date_completed' tooltip='Once the project has been completed, enter the date here.'></field>
            </column>
        </row>
        <row>
            <column>
                <field name='groups' hidelabel='true'></field>
            </column>
        </row>

    </column>
</row>