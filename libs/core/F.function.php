<?php

function ss_tidy_tags ($tags)
{
    $delimiter = TAG_DEFAULT_DELIMITER;
    $result = array();
    if (! empty($tags))
    {
        $tags = str_replace('"', $delimiter, $tags);
        $tags = str_replace("'", $delimiter, $tags);
        $tags = str_replace('，', $delimiter, $tags);
        $tags = str_replace(' ', $delimiter, $tags);
        $a_tags = explode($delimiter, $tags);
        foreach ($a_tags as $tag)
        {
            if (! empty($tag))
            {
                $result[md5($tag)] = trim($tag);
            }
        }
    }
    return implode($delimiter, array_values($result));
}

function ss_tidy_html ($content)
{
    //只保留部分tag
    $a_allowed_tags = array(
        '<div>' ,
        '<img>' ,
        '<br>' ,
        '<b>' ,
        '<p>' ,
        '<em>' ,
        '<strong>' ,
        '<font>' ,
        '<span>' ,
        '<a>' ,
        '<ul>' ,
        '<hr>' ,
        '<ol>' ,
        '<li>' ,
        '<table>' ,
        '<tr>' ,
        '<th>' ,
        '<td>' ,
        '<sup>' ,
        '<tbody>'
    );
    return SString::stripTagsAttributes($content, $a_allowed_tags);
}

function ss_text_only ($content)
{
    $a_allowed_tags = array();
    return SString::stripTagsAttributes($content, $a_allowed_tags);
}

function ss_utf8_gbk ($content)
{
    return SConverter::utf82gbk($content);
}

function ss_gbk_utf8 ($content)
{
    return SConverter::gbk2utf8($content);
}

/**
 * 获取客户端真实 ip, 考虑 F5, 代理 等情况
 *
 * @return $realip 用户真实ip
 */
function ss_get_client_ip ($return_is_int = false)
{
    if (isset($_SERVER))
    {
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        {
            $realip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        elseif (isset($_SERVER['HTTP_CLIENT_IP']))
        {
            $realip = $_SERVER['HTTP_CLIENT_IP'];
        }
        else
        {
            $realip = @$_SERVER['REMOTE_ADDR'];
        }
    }
    else
    {
        if (getenv("HTTP_X_FORWARDED_FOR"))
        {
            $realip = getenv("HTTP_X_FORWARDED_FOR");
        }
        elseif (getenv("HTTP_CLIENT_IP"))
        {
            $realip = getenv("HTTP_CLIENT_IP");
        }
        else
        {
            $realip = getenv("REMOTE_ADDR");
        }
    }
    if (false != $return_is_int)
    {
        return ip2long($realip);
    }
    else
    {
        return $realip;
    }
}
