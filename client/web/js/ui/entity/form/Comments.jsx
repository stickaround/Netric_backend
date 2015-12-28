/**
 * Component that handles rendering both an add comment form and comments list into an entity form
 *
 * @jsx React.DOM
 */
'use strict';

var React = require('react');
var ReactDOM = require('react-dom');
var EntityCommentsController = require('../../../controller/EntityCommentsController');
var controller = require("../../../controller/controller");
var netric = require("../../../base");
var Device = require("../../../Device");
var Chamel = require("chamel");
var IconButton = Chamel.IconButton;
var FlatButton = Chamel.FlatButton;

/**
 * Constant indicating the smallest device that we can print comments in
 *
 * All other devices will open comments in a dialog when clicked
 *
 * @type {number}
 * @private
 */
var _minimumInlineDeviceSize = Device.sizes.large;

/**
 * Render comments into an entity form
 */
var Comments = React.createClass({

    propTypes: {
        xmlNode: React.PropTypes.object,
        entity: React.PropTypes.object,
        eventsObj: React.PropTypes.object,
        editMode: React.PropTypes.bool
    },

    getInitialState: function () {
        return {
            commentsController: null,
            numComments: this.props.entity.getValue('num_comments'),
            entityId: this.props.entity.id
        }
    },

    componentDidMount: function () {
        this._loadComments();
    },


    /**
     * Determine if we should update the component or not
     *
     * @param {nextProps} object    Contains the next props for the update
     * @param {nextState} object    Contains the next state for the update
     *
     * @return {bool}   True if we want to update the component and false if we dont want to update
     */
    shouldComponentUpdate: function (nextProps, nextState) {
        var numComments = nextProps.entity.getValue('num_comments') || 0;

        // If entity id is changed (entity was newly created) then we will load the comments section
        if (this.state.entityId != nextProps.entity.id) {

            // Update the state's entityId and numComments
            this.setState({
                entityId: nextProps.entity.id,
                numComments: numComments
            })
            return true;
        }

        // Only load the comments if the previous num_comments and current entity's num_comments are not equal
        if (this.state.numComments != numComments) {
            this.setState({numComments: numComments})
            return true;
        }

        return false;
    },

    /**
     * Invoked immediately after the component's updates are flushed to the DOM.
     *
     * This method is not called for the initial render and we use it to check if
     * the id has changed - meaning a new entity was saved.
     */
    componentDidUpdate: function () {
        this._loadComments();
    },

    render: function () {

        var numCommentsLabel = "0 Comments";

        if (this.props.entity.getValue("num_comments")) {
            numCommentsLabel = this.props.entity.getValue("num_comments") + " Comments";
        }

        var content = null;

        // Smaller devices should print number of comments and a link
        if (netric.getApplication().device.size < _minimumInlineDeviceSize) {
            content = (
                <div>
                    <IconButton
                        onClick={this._loadComments}
                        iconClassName="fa fa-comment-o"
                        />
                    <FlatButton
                        label={numCommentsLabel}
                        onClick={this._loadComments}
                        />
                </div>
            );
        }

        // If this is a new entity display nothing
        if (!this.props.entity.id) {
            content = <div />;
        }

        return (<div ref="comcon">{content}</div>);


    },

    /**
     * Load the comments controller either inline or as dialog for smaller devices
     *
     * @private
     */
    _loadComments: function () {

        // Only load comments if this device displays inline comments (size > medium)
        if (netric.getApplication().device.size < _minimumInlineDeviceSize) {
            return;
        }

        // We only display comments if workign with an actual entity
        if (!this.props.entity.id) {
            return;
        }

        // Check if we have already loaded the controller
        if (this.state.commentsController) {
            // Just refresh the results and return
            this.state.commentsController.refresh();
            return;
        }

        // Default to a dialog
        var controllerType = controller.types.DIALOG;
        var inlineCon = null;
        var hideAppBar = false;

        // If we are on a larger device then print inline
        if (netric.getApplication().device.size >= _minimumInlineDeviceSize) {
            controllerType = controller.types.FRAGMENT;
            inlineCon = ReactDOM.findDOMNode(this.refs.comcon);
            hideAppBar = true;
        }

        var comments = new EntityCommentsController();
        comments.load({
            type: controllerType,
            title: "Comments",
            objType: "comment",
            hideAppBar: hideAppBar,
            objReference: this.props.entity.objType + ":" + this.props.entity.id
        }, inlineCon);

        this.setState({commentsController: comments});
    }
});

module.exports = Comments;
