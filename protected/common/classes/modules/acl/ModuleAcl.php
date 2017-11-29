<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 * Based on
 *   LiveStreet Engine Social Networking by Mzhelskiy Maxim
 *   Site: www.livestreet.ru
 *   E-mail: rus.engine@gmail.com
 *----------------------------------------------------------------------------
 */

/**
 * ACL (Access Control List)
 * Модуль контроля доступа пользователей к определенным возможностям/функциям
 *
 * @package modules.acl
 * @since   1.0
 */
class ModuleACL extends Module {
    /**
     * Коды ответов на запрос о возможности
     * пользователя голосовать за блог
     */
    const CAN_VOTE_BLOG_FALSE = 0;
    const CAN_VOTE_BLOG_TRUE = 1;
    const CAN_VOTE_BLOG_ERROR_CLOSE = 2;
    const CAN_VOTE_BLOG_ERROR_BAN = 3;
    /**
     * Коды ответов на запрос о возможности
     * пользователя голосовать за топик
     */
    const CAN_VOTE_TOPIC_FALSE = 0;
    const CAN_VOTE_TOPIC_TRUE = 1;
    const CAN_VOTE_TOPIC_ERROR_BAN = 2;
    const CAN_VOTE_TOPIC_NOT_IS_PUBLISHED = 3;
    /**
     * Коды ответов на запрос о возможности
     * пользователя голосовать за комментарий
     */
    const CAN_VOTE_COMMENT_FALSE = 0;
    const CAN_VOTE_COMMENT_TRUE = 1;
    const CAN_VOTE_COMMENT_ERROR_BAN = 2;
    /**
     * Коды ответов на запрос о возможности
     * пользователя оставлять комментарий
     */
    const CAN_TOPIC_COMMENT_FALSE = 0;
    const CAN_TOPIC_COMMENT_TRUE = 1;
    const CAN_TOPIC_COMMENT_ERROR_BAN = 2;
    /**
     * Коды механизма удаления блога
     */
    const CAN_DELETE_BLOG_EMPTY_ONLY = 1;
    const CAN_DELETE_BLOG_WITH_TOPICS = 2;

    /**
     * Инициализация модуля
     *
     */
    public function init() {

    }

    /**
     * Проверяет может ли пользователь создавать блоги
     *
     * @param ModuleUser_EntityUser $oUser    Пользователь
     *
     * @return bool
     */
    public function canCreateBlog($oUser) {

        if ($oUser) {
            if ($oUser->isAdministrator() || $oUser->isModerator()) {
                return true;
            }
            if ($oUser->getRating() >= \C::get('acl.create.blog.rating')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Проверяет может ли пользователь создавать топики в определенном блоге
     *
     * @param ModuleUser_EntityUser $oUser    Пользователь
     * @param ModuleBlog_EntityBlog|null $oBlog    Блог
     *
     * @return bool
     */
    public function canAddTopic($oUser, $oBlog) {

        if (!$oUser) {
            return false;
        }
        // Если у пользователя нет ни одного блога, то в эту переменную
        // передаётся null, соответственно, постинг в никуда запрещаем
        if (is_null($oBlog)) {
            return false;
        }
        // * Если юзер является создателем блога то разрешаем ему постить
        if ($oUser->isAdministrator() || $oUser->isModerator() || ($oUser->getId() == $oBlog->getOwnerId())) {
            return true;
        }
        // * Если рейтинг юзера больше либо равен порогу постинга в блоге то разрешаем постинг
        if ($oUser->getRating() >= $oBlog->getLimitRatingTopic()) {
            return true;
        }
        return false;
    }

    /**
     * Проверяет может ли пользователь создавать комментарии
     *
     * @param ModuleUser_EntityUser $oUser
     * @param ModuleTopic_EntityTopic $oTopic
     *
     * @return bool
     */
    public function canPostComment($oUser, $oTopic) {

        if (!$oUser) {
            return self::CAN_TOPIC_COMMENT_FALSE;
        }

        if ($oUser->isAdministrator() || $oUser->isModerator()) {
            return self::CAN_TOPIC_COMMENT_TRUE;
        }

        if ($oTopic) {
            $oBlog = $oTopic->getBlog();
            if ($oBlog && $oBlog->getBlogType()) {
                $oBlogUser = \E::Module('Blog')->getBlogUserByBlogIdAndUserId($oBlog->getId(), $oUser->getId());
                if ($oBlogUser && $oBlogUser->getUserRole() == ModuleBlog::BLOG_USER_ROLE_BAN) {
                    return self::CAN_TOPIC_COMMENT_ERROR_BAN;
                }
                if ($oBlogUser && $oBlogUser->getUserRole() == ModuleBlog::BLOG_USER_ROLE_BAN_FOR_COMMENT) {
                    return self::CAN_TOPIC_COMMENT_ERROR_BAN;
                }
            }
            // * Проверяем на закрытый блог
            if (!$this->IsAllowShowBlog($oTopic->getBlog(), $oUser)) {
                return self::CAN_TOPIC_COMMENT_FALSE;
            }
            if ($oUser->getRating() >= \C::get('acl.create.comment.rating')) {
                return self::CAN_TOPIC_COMMENT_TRUE;
            }
        }

        return self::CAN_TOPIC_COMMENT_FALSE;
    }

    /**
     * Проверяет может ли пользователь создавать комментарии по времени(например ограничение максимум 1 коммент в 5 минут)
     *
     * @param ModuleUser_EntityUser $oUser    Пользователь
     *
     * @return bool
     */
    public function canPostCommentTime($oUser) {

        if (!$oUser) {
            return false;
        }
        if ($oUser->isAdministrator() || $oUser->isModerator()) {
            return true;
        }
        if (\C::get('acl.create.comment.limit_time') > 0 && $oUser->getDateCommentLast()) {
            $sDateCommentLast = strtotime($oUser->getDateCommentLast());
            if ($oUser->getRating() < \C::get('acl.create.comment.limit_time_rating')
                && ((time() - $sDateCommentLast) < \C::get('acl.create.comment.limit_time'))
            ) {
                return false;
            }
        }
        return true;
    }

    /**
     * Проверяет может ли пользователь создавать топик по времени
     *
     * @param  ModuleUser_EntityUser $oUser    Пользователь
     *
     * @return bool
     */
    public function canPostTopicTime($oUser) {

        if (!$oUser) {
            return false;
        }
        // Для администраторов ограничение по времени не действует
        if ($oUser->isAdministrator()
            || $oUser->isModerator()
            || \C::get('acl.create.topic.limit_time') == 0
            || $oUser->getRating() >= \C::get('acl.create.topic.limit_time_rating')
        ) {
            return true;
        }

        // * Проверяем, если топик опубликованный меньше чем acl.create.topic.limit_time секунд назад
        $aTopics = \E::Module('Topic')->getLastTopicsByUserId($oUser->getId(), \C::get('acl.create.topic.limit_time'));
        if (isset($aTopics['count']) && $aTopics['count'] > 0) {
            return false;
        }
        return true;
    }

    /**
     * Проверяет может ли пользователь отправить инбокс по времени
     *
     * @param  ModuleUser_EntityUser $oUser    Пользователь
     *
     * @return bool
     */
    public function canSendTalkTime($oUser) {

        if (!$oUser) {
            return false;
        }

        // Для администраторов ограничение по времени не действует
        if ($oUser->isAdministrator()
            || $oUser->isModerator()
            || \C::get('acl.create.talk.limit_time') == 0
            || $oUser->getRating() >= \C::get('acl.create.talk.limit_time_rating')
        ) {
            return true;
        }

        // * Проверяем, если топик опубликованный меньше чем acl.create.topic.limit_time секунд назад
        $aTalks = \E::Module('Talk')->getLastTalksByUserId($oUser->getId(), \C::get('acl.create.talk.limit_time'));
        if (isset($aTalks['count']) && $aTalks['count'] > 0) {
            return false;
        }
        return true;
    }

    /**
     * Проверяет может ли пользователь создавать комментарии к инбоксу по времени
     *
     * @param  ModuleUser_EntityUser $oUser    Пользователь
     *
     * @return bool
     */
    public function canPostTalkCommentTime($oUser) {

        if (!$oUser) {
            return false;
        }
        // * Для администраторов ограничение по времени не действует
        if ($oUser->isAdministrator()
            || $oUser->isModerator()
            || \C::get('acl.create.talk_comment.limit_time') == 0
            || $oUser->getRating() >= \C::get('acl.create.talk_comment.limit_time_rating')
        ) {
            return true;
        }

        // * Проверяем, если топик опубликованный меньше чем acl.create.topic.limit_time секунд назад
        $aTalkComments = \E::Module('Comment')->getCommentsByUserId($oUser->getId(), 'talk', 1, 1);

        // * Если комментариев не было
        if (!is_array($aTalkComments) || $aTalkComments['count'] == 0) {
            return true;
        }
        // * Достаем последний комментарий
        $oComment = array_shift($aTalkComments['collection']);
        $sDate = strtotime($oComment->getDate());

        if ($sDate && ((time() - $sDate) < \C::get('acl.create.talk_comment.limit_time'))) {
            return false;
        }
        return true;
    }

    /**
     * Проверяет может ли пользователь создавать комментарии используя HTML
     *
     * @param ModuleUser_EntityUser $oUser    Пользователь
     *
     * @return bool
     */
    public function canUseHtmlInComment($oUser) {

        return true;
    }

    /**
     * Проверяет может ли пользователь голосовать за конкретный комментарий
     *
     * @param ModuleUser_EntityUser       $oUser       Пользователь
     * @param ModuleComment_EntityComment $oComment    Комментарий
     *
     * @return bool
     */
    public function canVoteComment($oUser, ModuleComment_EntityComment $oComment) {

        if (!C::get('rating.enabled')) {
            return self::CAN_VOTE_COMMENT_FALSE;
        }
        /** @var ModuleBlog_EntityBlog $oBlog */
        $oBlog = $oComment->getTargetBlog();
        if ($oBlog && $oBlog->getBlogType()) {
            $oBlogUser = \E::Module('Blog')->getBlogUserByBlogIdAndUserId($oBlog->getId(), $oUser->getId());
            if ($oBlogUser && $oBlogUser->getUserRole() == ModuleBlog::BLOG_USER_ROLE_BAN) {
                return self::CAN_VOTE_COMMENT_ERROR_BAN;
            }
        }
        if ($oUser->getRating() >= \C::get('acl.vote.comment.rating')) {
            return self::CAN_VOTE_COMMENT_TRUE;
        }
        return self::CAN_VOTE_COMMENT_FALSE;
    }

    /**
     * Проверяет может ли пользователь голосовать за конкретный блог
     *
     * @param ModuleUser_EntityUser $oUser    Пользователь
     * @param ModuleBlog_EntityBlog $oBlog    Блог
     *
     * @return bool
     */
    public function canVoteBlog($oUser, ModuleBlog_EntityBlog $oBlog) {

        if (!C::get('rating.enabled')) {
            return self::CAN_VOTE_BLOG_FALSE;
        }
        $oBlogUser = \E::Module('Blog')->getBlogUserByBlogIdAndUserId($oBlog->getId(), $oUser->getId());
        if ($oBlogUser && $oBlogUser->getUserRole() == ModuleBlog::BLOG_USER_ROLE_BAN) {
            return self::CAN_VOTE_BLOG_ERROR_BAN;
        }
        // * Если блог приватный, проверяем является ли пользователь его читателем
        if ($oBlog->getBlogType() && $oBlog->getBlogType()->IsPrivate()) {
            if (!$oBlogUser || $oBlogUser->getUserRole() < ModuleBlog::BLOG_USER_ROLE_GUEST) {
                return self::CAN_VOTE_BLOG_ERROR_CLOSE;
            }
        }
        if ($oUser->getRating() >= \C::get('acl.vote.blog.rating')) {
            return self::CAN_VOTE_BLOG_TRUE;
        }
        return self::CAN_VOTE_BLOG_FALSE;
    }

    /**
     * Проверяет может ли пользователь голосовать за конкретный топик
     *
     * @param ModuleUser_EntityUser   $oUser     Пользователь
     * @param ModuleTopic_EntityTopic $oTopic    Топик
     *
     * @return bool
     */
    public function canVoteTopic($oUser, ModuleTopic_EntityTopic $oTopic) {

        if (!C::get('rating.enabled')) {
            return self::CAN_VOTE_TOPIC_FALSE;
        }
        if (!$oTopic->getPublish()) {
            return self::CAN_VOTE_TOPIC_NOT_IS_PUBLISHED;
        }
        $oBlog = $oTopic->getBlog();
        if ($oBlog && $oBlog->getBlogType()) {
            $oBlogUser = \E::Module('Blog')->getBlogUserByBlogIdAndUserId($oBlog->getId(), $oUser->getId());
            if ($oBlogUser && $oBlogUser->getUserRole() == ModuleBlog::BLOG_USER_ROLE_BAN) {
                return self::CAN_VOTE_TOPIC_ERROR_BAN;
            }
        }
        if ($oUser->getRating() >= \C::get('acl.vote.topic.rating')) {
            return self::CAN_VOTE_TOPIC_TRUE;
        }
        return self::CAN_VOTE_TOPIC_FALSE;
    }

    /**
     * Проверяет может ли пользователь голосовать за конкретного пользователя
     *
     * @param ModuleUser_EntityUser $oUser          Пользователь
     * @param ModuleUser_EntityUser $oUserTarget    Пользователь за которого голосуем
     *
     * @return bool
     */
    public function canVoteUser($oUser, ModuleUser_EntityUser $oUserTarget)
    {
        if (!C::get('rating.enabled')) {
            return false;
        }
        if ($oUser->getRating() >= \C::get('acl.vote.user.rating')) {
            return true;
        }
        return false;
    }

    /**
     * Проверяет можно ли юзеру слать инвайты
     *
     * @param ModuleUser_EntityUser $oUser    Пользователь
     *
     * @return bool
     */
    public function canSendInvite($oUser)
    {
        if (\E::Module('User')->getCountInviteAvailable($oUser) == 0) {
            return false;
        }
        return true;
    }

    /**
     * Можно ли юзеру постить в данный блог
     *
     * @param ModuleBlog_EntityBlog $oBlog    Блог
     * @param ModuleUser_EntityUser $oUser    Пользователь
     *
     * @return bool
     */
    public function isAllowBlog($oBlog, $oUser)
    {
        if ($oUser && ($oUser->isAdministrator() ||$oUser->isModerator() || $oBlog->getOwnerId() == $oUser->getId())) {
            return true;
        }
        if ($oUser->getRating() <= \C::get('acl.create.topic.limit_rating')) {
            return false;
        }

        return (bool)E::Module('Blog')->getBlogsAllowTo('write', $oUser, $oBlog->getId(), true) && $this->CanAddTopic($oUser, $oBlog);
    }

    /**
     * Проверяет можно или нет юзеру просматривать блог
     *
     * @param ModuleBlog_EntityBlog $oBlog    Блог
     * @param ModuleUser_EntityUser $oUser    Пользователь
     *
     * @return bool
     */
    public function isAllowShowBlog($oBlog, $oUser) {

        if ($oUser && ($oUser->isModerator() ||$oUser->isAdministrator() || $oBlog->getOwnerId() == $oUser->getId())) {
            return true;
        }
        if ($oBlogType = $oBlog->getBlogType()) {
            if ($oBlogType->GetAclRead(ModuleBlog::BLOG_USER_ACL_GUEST)) {
                return true;
            } elseif ($oBlogType->GetAclRead(ModuleBlog::BLOG_USER_ACL_USER)) {
                return $oUser ? true : false;
            }
        }

        return (bool)E::Module('Blog')->getBlogsAllowTo('read', $oUser, $oBlog, true);
    }

    /**
     * Проверяет можно или нет пользователю редактировать данный топик
     *
     * @param  ModuleTopic_EntityTopic $oTopic    Топик
     * @param  ModuleUser_EntityUser   $oUser     Пользователь
     *
     * @return bool
     */
    public function isAllowEditTopic($oTopic, $oUser) {

        if (!$oTopic || !$oUser) {
            return false;
        }
        // * Разрешаем если это админ сайта или автор топика
        if ($oTopic->getUserId() == $oUser->getId() || $oUser->isAdministrator() || $oUser->isModerator()) {
            return true;
        }
        // * Если владелец блога
        if ($oTopic->getBlog()->getOwnerId() == $oUser->getId()) {
            return true;
        }

        // Проверяем права
        return $this->CheckBlogEditContent($oTopic->getBlog(), $oUser);
    }

    /**
     * Проверяет можно или нет пользователю удалять данный топик
     *
     * @param ModuleTopic_EntityTopic $oTopic    Топик
     * @param ModuleUser_EntityUser   $oUser     Пользователь
     *
     * @return bool
     */
    public function isAllowDeleteTopic($oTopic, $oUser) {

        if (!$oTopic || !$oUser) {
            return false;
        }
        // * Разрешаем если это админ сайта или автор топика
        if ($oTopic->getUserId() == $oUser->getId() || $oUser->isAdministrator() || $oUser->isModerator()) {
            return true;
        }
        // * Если владелец блога
        if ($oTopic->getBlog()->getOwnerId() == $oUser->getId()) {
            return true;
        }

        // Проверяем права
        return $this->CheckBlogDeleteContent($oTopic->getBlog(), $oUser);
    }

    /**
     * Проверяет может ли пользователь удалить комментарий
     *
     * @param  ModuleUser_EntityUser $oUser    Пользователь
     *
     * @return bool
     */
    public function canDeleteComment($oUser) {

        if (!$oUser || !($oUser->isAdministrator() && $oUser->isModerator())) {
            return false;
        }
        return true;
    }

    /**
     * Проверяет может ли пользователь публиковать на главной
     *
     * @param  ModuleUser_EntityUser $oUser    Пользователь
     *
     * @return bool
     */
    public function isAllowPublishIndex($oUser) {

        if ($oUser->isAdministrator() || $oUser->isModerator()) {
            return true;
        }
        return false;
    }

    /**
     * Проверяет можно или нет пользователю управлять пользователями блога
     *
     * @param  ModuleBlog_EntityBlog $oBlog    Блог
     * @param  ModuleUser_EntityUser $oUser    Пользователь
     *
     * @return bool
     */
    public function isAllowAdminBlog($oBlog, $oUser) {

        if ($oUser->isAdministrator() || $oUser->isModerator()) {
            return true;
        }
        // * Разрешаем если это владелец блога
        if ($oBlog->getOwnerId() == $oUser->getId()) {
            return true;
        }

        // Проверка прав на администрирование
        return $this->CheckBlogControlUsers($oBlog, $oUser);
    }

    /**
     * Проверяет можно или нет пользователю редактировать данный блог
     *
     * @param  ModuleBlog_EntityBlog $oBlog    Блог
     * @param  ModuleUser_EntityUser $oUser    Пользователь
     *
     * @return bool
     */
    public function isAllowEditBlog($oBlog, $oUser) {

        if ($oUser->isAdministrator() || $oUser->isModerator()) {
            return true;
        }
        // Разрешаем если это владелец блога
        if ($oBlog->getOwnerId() == $oUser->getId()) {
            return true;
        }

        // Проверка прав на редактирование
        return $this->CheckBlogEditBlog($oBlog, $oUser);
    }

    /**
     * Проверяет можно или нет пользователю удалять данный блог
     *
     * @param ModuleBlog_EntityBlog $oBlog    Блог
     * @param ModuleUser_EntityUser $oUser    Пользователь
     *
     * @return bool|int
     */
    public function isAllowDeleteBlog($oBlog, $oUser)
    {
        // * Разрешаем если это админ сайта
        if ($oUser->isAdministrator() || $oUser->isModerator()) {
            return self::CAN_DELETE_BLOG_WITH_TOPICS;
        }
        // * Разрешаем владелецу, но только пустой
        if ($oBlog->getOwnerId() == $oUser->getId()) {
            return self::CAN_DELETE_BLOG_EMPTY_ONLY;
        }

        $oBlogUser = \E::Module('Blog')->getBlogUserByBlogIdAndUserId($oBlog->getId(), $oUser->getId());
        if ($oBlogUser && $oBlogUser->IsBlogAdministrator()) {
            return self::CAN_DELETE_BLOG_EMPTY_ONLY;
        }
        return false;
    }

    /**
     * Проверка на ограничение по времени на постинг на стене
     *
     * @param ModuleUser_EntityUser $oUser    Пользователь
     * @param ModuleWall_EntityWall $oWall    Объект сообщения на стене
     *
     * @return bool
     */
    public function canAddWallTime($oUser, $oWall)
    {
        // * Для администраторов ограничение по времени не действует
        if ($oUser->isAdministrator()
            || $oUser->isModerator()
            || \C::get('acl.create.wall.limit_time') == 0
            || $oUser->getRating() >= \C::get('acl.create.wall.limit_time_rating')
        ) {
            return true;
        }
        if ($oWall->getUserId() == $oWall->getWallUserId()) {
            return true;
        }
        // * Получаем последнее сообщение
        $aWall = \E::Module('Wall')->getWall(array('user_id' => $oWall->getUserId()), array('id' => 'desc'), 1, 1, array());
        // * Если сообщений нет
        if ($aWall['count'] == 0) {
            return true;
        }

        $oWallLast = array_shift($aWall['collection']);
        $sDate = strtotime($oWallLast->getDateAdd());
        if ($sDate && ((time() - $sDate) < \C::get('acl.create.wall.limit_time'))) {
            return false;
        }
        return true;
    }

    /************************************************************
     * Набор методов для управления/проверки прав пользователей
     *
     * Права пользователей определяются ролями, которые могут быть объединены в группы
     *
     * На текущем этапе определяется только одна группа 'blogs', внутри которой есть роли 'administrator' и 'moderator',
     * это права администраторов и модераторов блогов
     */

    /**
     * Вспомогательный метод получения прав пользователей
     *
     * @return array
     */
    protected function _getAllUserRights()
    {
        $aRights = \C::get('rights');
        // Если права не заданы в конфиге, то задаем права по умолчанию для группы 'blogs'
        if (!$aRights) {
            $aRights = array(
                'blogs' => array(
                    'administrator' => array(
                        'control_users'  => true,
                        'edit_blog'      => true,
                        'edit_content'   => true,
                        'delete_content' => true,
                        'edit_comment'   => true,
                        'delete_comment' => true,
                    ),
                    'moderator'     => array(
                        'control_users'  => false,
                        'edit_blog'      => false,
                        'edit_content'   => true,
                        'del_content'    => false,
                        'edit_comment'   => true,
                        'delete_comment' => true,
                    ),
                ),
            );
        }
        return $aRights;
    }

    /**
     * Получение прав для конкретной группы и, опционально, роли
     *
     * @param string $sGroup
     * @param string $sRole
     *
     * @return array
     */
    public function getUserRights($sGroup, $sRole = null)
    {
        $aRights = $this->_getAllUserRights();
        if (isset($aRights[$sGroup])) {
            $aRights = $aRights[$sGroup];
            if ($sRole && isset($aRights[$sRole])) {
                $aRights = $aRights[$sRole];
            }
        } else {
            $aRights = [];
        }
        return $aRights;
    }

    /**
     * Вспомогательный метод проверки прав пользователя блога
     *
     * @param ModuleBlog_EntityBlog $oBlog
     * @param ModuleUser_EntityUser $oUser
     * @param string                $sRights
     *
     * @return bool
     */
    protected function _checkBlogUserRights($oBlog, $oUser, $sRights) {

        $sUserRole = '';
        $bCurrentUser = false;
        $bResult = false;

        if (!$oBlog) {
            return false;
        }

        // Если пользователь не передан, то берется текущий
        if (!$oUser) {
            if ($oUser = \E::User()) {
                $bCurrentUser = true;
            } else {
                return false;
            }
        } elseif (\E::User() &&   \E::User()->getId() == $oUser->getId()) {
            $bCurrentUser = true;
        }

        $sCacheKey = 'acl_blog_user_rights' . serialize(array($oBlog->GetId(), $oUser ? $oUser->GetId() : 0, $bCurrentUser, $sRights));
        // Сначала проверяем кеш
        if (is_int($xCacheResult = \E::Module('Cache')->get($sCacheKey, 'tmp'))) {
            return $xCacheResult;
        }

        if ($bCurrentUser) {
            // Blog owner has any rights
            if ($oBlog->getUserOwnerId() == $oUser->getId()) {
                return true;
            }

            // * Для авторизованного пользователя данный код будет работать быстрее
            if ($oBlog->getUserIsAdministrator()) {
                $sUserRole = 'administrator';
            } elseif ($oBlog->getUserIsModerator()) {
                $sUserRole = 'moderator';
            }
        } else {
            $oBlogUser = \E::Module('Blog')->getBlogUserByBlogIdAndUserId($oBlog->getId(), $oUser->getId());
            if ($oBlogUser) {
                if ($oBlogUser->IsBlogAdministrator()) {
                    $sUserRole = 'administrator';
                } elseif ($oBlogUser->IsBlogModerator()) {
                    $sUserRole = 'moderator';
                }
            }
        }

        if ($sUserRole) {
            $aUserRights = $this->GetUserRights('blogs', $sUserRole);
            $bResult = isset($aUserRights[$sRights]) && (bool)$aUserRights[$sRights];
        }

        \E::Module('Cache')->set($sCacheKey, $bResult ? 1 : 0, array('blog_update', 'user_update'), 0, 'tmp');

        return $bResult;
    }

    /**
     * Право на управление пользователями блога
     *
     * @param ModuleBlog_EntityBlog $oBlog
     * @param ModuleUser_EntityUser $oUser - если не задано, то берется текущий авторизованный пользователь
     *
     * @return bool
     */
    public function checkBlogControlUsers($oBlog, $oUser = null) {

        return $this->_checkBlogUserRights($oBlog, $oUser, 'control_users');
    }

    /**
     * Право на редактирование блога
     *
     * @param ModuleBlog_EntityBlog $oBlog
     * @param ModuleUser_EntityUser $oUser - если не задано, то берется текущий авторизованный пользователь
     *
     * @return bool
     */
    public function checkBlogEditBlog($oBlog, $oUser = null) {

        return $this->_checkBlogUserRights($oBlog, $oUser, 'edit_blog');
    }

    /**
     * Право на редактирование контента блога
     *
     * @param ModuleBlog_EntityBlog $oBlog
     * @param ModuleUser_EntityUser $oUser - если не задано, то берется текущий авторизованный пользователь
     *
     * @return bool
     */
    public function checkBlogEditContent($oBlog, $oUser = null) {

        return $this->_checkBlogUserRights($oBlog, $oUser, 'edit_content');
    }

    /**
     * Право на удаление контента блога
     *
     * @param ModuleBlog_EntityBlog $oBlog
     * @param ModuleUser_EntityUser $oUser - если не задано, то берется текущий авторизованный пользователь
     *
     * @return bool
     */
    public function checkBlogDeleteContent($oBlog, $oUser = null) {

        return $this->_checkBlogUserRights($oBlog, $oUser, 'delete_content');
    }

    /**
     * Право на редактирование комментариев блога
     *
     * @param ModuleBlog_EntityBlog $oBlog
     * @param ModuleUser_EntityUser $oUser - если не задано, то берется текущий авторизованный пользователь
     *
     * @return bool
     */
    public function checkBlogEditComment($oBlog, $oUser = null) {

        return $this->_checkBlogUserRights($oBlog, $oUser, 'edit_comment');
    }

    /**
     * Право на удаление комментариев блога
     *
     * @param ModuleBlog_EntityBlog $oBlog
     * @param ModuleUser_EntityUser $oUser - если не задано, то берется текущий авторизованный пользователь
     *
     * @return bool
     */
    public function checkBlogDeleteComment($oBlog, $oUser = null) {

        return $this->_checkBlogUserRights($oBlog, $oUser, 'delete_comment');
    }

}

// EOF