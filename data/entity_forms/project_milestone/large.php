<row>
    <column>
        <tabs>
            <tab name='General'>
                <row>
                    <column>
                        <field name='name'></field>
                    </column>
                </row>
                <row>
                    <column type='quarter'>
                        <field name='date_start'></field>
                        <field name='deadline'></field>
                        <field name='project_id'></field>
                        <field name='owner_id'></field>
                        <field name='f_completed'></field>
                    </column>
                </row>
                <row>
                    <column>
                        <field name='notes' hidelabel='true' multiline='true'></field>
                    </column>
                </row>
                <row>
                    <column>
                        <field name='comments'></field>
                    </column>
                </row>
            </tab>
            <tab name='Activity'>
                <field name='activity'></field>
            </tab>
            <tab name='Tasks'>
                <objectsref name='Tasks' obj_type='task' ref_field='milestone_id'></objectsref>
            </tab>

            <tab name='Discussions'>
                <objectsref obj_type='discussion'></objectsref>
            </tab>
        </tabs>
    </column>
</row>