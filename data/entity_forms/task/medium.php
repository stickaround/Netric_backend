<tabs>
    <tab name='General'>
        <row>
            <column>
                <field name='name' class='headline'></field>
            </column>
        </row>
        <fieldset name='Details'>

            <row>
                <column>
                    <field name='status_id'></field>
                    <field name='deadline' tooltip='Optional due date for the completion of this task.'></field>
                    <field name='priority_id'></field>
                    <field name='type_id'></field>
                    <recurrence></recurrence>
                </column>
                <column>
                    <field name='owner_id' tooltip='When creating a new task, this is by default assigned to you. <br/>However, you can assign new or existing tasks to someone else simply by changing the user. <br/>They will receive a notification when you first assign it to them, and you will be notified when the task is completed.'></field>
                    <field name='depends_task_id' filter='project' tooltip='Optional task that needs to be completed before this task can be worked on.'></field>
                    <field name='milestone_id' ref_field='project_id' ref_this='project' ref_required='t' tooltip='Milestones can be added to projects to split subsets of tasks into shorter periods. <br/>The task must first be a member of a project before being assigned to a milestone.'></field>
                    <field name='story_id' ref_field='project_id' ref_this='project' ref_required='t' tooltip='User stories are used in Agile Project Managment to track potential work to be done and <br/>tasks are used to assign the work to users.'></field>
                </column>
                <column>
                    <field name='contact_id'></field>
                    <field name='cost_estimated' tooltip='Use this to track your effeciency. Enter the amount of time you estimate this task to take in hours. <br/>Use decimals to divide smaller intervals. For instance ".5" would be 30 minutes or 1/2 hour.'></field>
                    <field name='cost_actual' tooltip='This is automatically calculated as you work on the task. <br/>After saving changes, click "Log Time Spent" in the toolbar for this task to record how much actual time you spent on the task.'></field>
                    <field name='date_completed'></field>
                    <field name='creator_id'></field>
                    <field name='obj_reference'></field>
                </column>
            </row>
        </fieldset>
        <row>
            <column>
                <attachments></attachments>
            </column>
        </row>
        <row>
            <column>
                <field name='notes' hidelabel='true' multiline='true'></field>
            </column>
        </row>
        <row>
            <column>
                <comments />
            </column>
        </row>
    </tab>

    <tab name='Activity'>
        <field name='activity'></field>
    </tab>

    <tab name='Dependent Tasks'>
        <objectsref obj_type='task' ref_field='depends_task_id'></objectsref>
    </tab>

    <tab name='Time'>
        <objectsref obj_type='time' ref_field='task_id'></objectsref>
    </tab>
</tabs>