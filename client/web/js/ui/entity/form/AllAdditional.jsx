/**
 * All additional/custom fields
 *
 */
'use strict';

var React = require('react');
var Field = require('./Field.jsx');
var FormNode = require('../../../entity/form/FormNode');

/**
 * All additional will gather all custom (non-system) fields and print them
 *
 * This allows users to add custom fields without having to worry about placing
 * them into a form.
 */
var AllAdditional = React.createClass({

    /**
     * Expected props
     */
    propTypes: {

        /**
         * Current element node level
         *
         * @type {entity/form/Node}
         */
        elementNode: React.PropTypes.object,

        /**
         * Entity being edited
         *
         * @type {entity\Entity}
         */
        entity: React.PropTypes.object,

        /**
         * Generic object used to pass events back up to controller
         *
         * @type {Object}
         */
        eventsObj: React.PropTypes.object,

        /**
         * Flag indicating if we are in edit mode or view mode
         *
         * @type {bool}
         */
        editMode: React.PropTypes.bool
    },

    render: function () {

        let fields = this.props.entity.def.getFields();
        let displayFields = [];

        // Make sure we have the collection of fields from entity definition
        if (fields) {
            fields.map(function (field, idx) {

                // Make sure that we have useWhen field attribute and the field is not a system field
                if (!field.system) {

                    // Get the decoded value of useWhen, if it is available
                    let useWhenObj = field.getDecodedUseWhen();

                    // If the useWhen value did not match with the entity field, then let's return and move to the next field
                    if (field.useWhen && this.props.entity.getValue(useWhenObj.name) != useWhenObj.value) {
                        return;
                    }

                    // Create an instance of node model so we can render the field element
                    let elementNode = new FormNode('Field');

                    // Set the name attribute for this element node
                    elementNode.setAttribute('name', field.name);

                    displayFields.push(
                        <Field
                            key={idx}
                            elementNode={elementNode}
                            entity={this.props.entity}
                            eventsObj={this.props.eventsObj}
                            editMode={this.props.editMode}
                            />
                    )
                }
            }.bind(this))
        }

        return (
            <div className="entity-form-field">
                {displayFields}
            </div>
        );
    }
});

module.exports = AllAdditional;