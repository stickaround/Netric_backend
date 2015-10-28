/**
 * Lit Item used where object type is 'comment'
 *
 * @jsx React.DOM
 */
'use strict';

var React = require('react');
var Chamel = require('chamel');
var Checkbox = Chamel.Checkbox;
var UserProfileImage = require("../../UserProfileImage.jsx");

/**
 * List item for a comment
 */
var CommentItem = React.createClass({

    render: function () {
        var entity = this.props.entity;

        var userId = entity.getValue("owner_id");
        var headerText = entity.getValueName("owner_id", userId);
        var headerTime = entity.getTime(null, true);
        var actors = entity.getActors();
        var comment = this._processCommentText(entity.getValue("comment"));

        return (
            <div className="entity-browser-comment">
                <div className="entity-browser-comment-img">
                    <UserProfileImage width={32} userId={userId} />
                </div>
                <div className="entity-browser-comment-details">
                    <div className="entity-browser-comment-header">
                        {headerText}
                        <div className="entity-browser-comment-time">
                            {headerTime}
                        </div>
                    </div>
                    <div className="entity-browser-comment-body">
                        <div dangerouslySetInnerHTML={comment} />
                    </div>
                </div>
            </div>
        );
    },

    /**
     * Render text to HTML for viewing
     *
     * @param {string} val The value to process
     * @param {bool} multiline If true allow new lines
     * @param {bool} rich If true allow html/rich text
     */
    _processCommentText: function(comment) {

        // Convert new lines to line breaks
        if (comment) {
            var re = new RegExp ("\n", 'gi') ;
            comment = comment.replace(re, "<br />");
        }

        // Convert email addresses into mailto links?
        //fieldValue = this._activateLinks(fieldValue);

        /*
         * TODO: Make sanitized html object. React requires this because
         * setting innherHTML is a pretty dangerous option in that it
         * is often used for cross script exploits.
         */
        return (comment) ? { __html: comment } : null;
    },

});

module.exports = CommentItem;