<row>
    <column>
        <row>
            <column>
                <field name='name' hidelabel="true" class='headline'></field>
            </column>
        </row>
        <row>
            <column>
                <field name='parent_id'></field>
                <field name='type_id'></field>
                <field name='status_id'></field>
            </column>
            <column>
                <field name='date_start'></field>
                <field name='date_end'></field>
                <field name='date_completed'></field>
            </column>
        </row>
        <row>
            <column>
                <field name='cost_estimated'></field>
                <field name='cost_actual'></field>
                <field name='rev_estimated'></field>
                <field name='rev_actual'></field>
            </column>
            <column>
                <field name='num_sent'></field>
                <field name='resp_estimated'></field>
                <field name='resp_actual'></field>
            </column>
        </row>
        <row>
            <column>
                <attachments></attachments>
            </column>
        </row>
        <row>
            <column>
                <field name='description' hidelabel='true' multiline='true'></field>
            </column>
        </row>
        <row>
            <column>
                <field name='comments' hidelabel='true'></field>
            </column>
        </row>
    </column>
    <column type="sidebar">
        <row>
            <fieldset name='Activity'>
                <field name='activity'></field>
            </fieldset>
        </row>
    </column>
</row>