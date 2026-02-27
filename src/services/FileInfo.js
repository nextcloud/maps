import axios from '@nextcloud/axios'

/**
 * Fetches file info using PROPFIND
 * @param {string} url
 */
export default async function(url) {
    const response = await axios({
        method: 'PROPFIND',
        url,
        headers: {
            Depth: 0,
        },
        data: `<?xml version="1.0"?>
            <d:propfind xmlns:d="DAV:"
                        xmlns:oc="http://owncloud.org/ns"
                        xmlns:nc="http://nextcloud.org/ns"
                        xmlns:ocs="http://open-collaboration-services.org/ns">
                <d:prop>
                    <d:getlastmodified />
                    <d:getetag />
                    <d:getcontenttype />
                    <d:resourcetype />
                    <oc:fileid />
                    <oc:permissions />
                    <oc:size />
                    <d:getcontentlength />
                    <nc:has-preview />
                    <nc:mount-type />
                    <nc:is-encrypted />
                    <ocs:share-permissions />
                    <oc:tags />
                    <oc:favorite />
                    <oc:comments-unread />
                    <oc:owner-id />
                    <oc:owner-display-name />
                    <oc:share-types />
                </d:prop>
            </d:propfind>`,
    })

    const parser = new DOMParser()
    const xml = parser.parseFromString(response.data, 'text/xml')

    const responseNode = xml.querySelector('d\\:response, response')
    if (!responseNode) {
        throw new Error('Invalid DAV response')
    }

    const getText = (selector) => {
        const el = responseNode.querySelector(selector)
        return el ? el.textContent : null
    }

    const getBool = (selector) => {
        const value = getText(selector)
        return value === 'true' || value === '1'
    }

    const getInt = (selector) => {
        const value = getText(selector)
        return value ? parseInt(value, 10) : 0
    }

    const isDirectory =
        !!responseNode.querySelector('d\\:resourcetype d\\:collection')

    const fileInfo = {
        // Core
        id: getText('oc\\:fileid'),
        etag: getText('d\\:getetag')?.replace(/"/g, ''),
        mimetype: isDirectory
            ? 'httpd/unix-directory'
            : getText('d\\:getcontenttype'),
        size: getInt('d\\:getcontentlength') || getInt('oc\\:size'),
        lastModified: getText('d\\:getlastmodified'),

        // Permissions
        permissions: getText('oc\\:permissions'),
        sharePermissions: getInt('ocs\\:share-permissions'),

        // Owner
        ownerId: getText('oc\\:owner-id'),
        ownerDisplayName: getText('oc\\:owner-display-name'),

        // Sharing
        shareTypes: getText('oc\\:share-types'),

        // Tags & metadata
        tags: getText('oc\\:tags'),
        favorite: getBool('oc\\:favorite'),
        commentsUnread: getInt('oc\\:comments-unread'),

        // Nextcloud-specific
        hasPreview: getBool('nc\\:has-preview'),
        mountType: getText('nc\\:mount-type'),
        isEncrypted: getBool('nc\\:is-encrypted'),

        // Raw resourcetype
        resourcetype: isDirectory ? 'collection' : 'file',
    }

    // Legacy Backbone compatibility
    fileInfo.get = (key) => fileInfo[key]
    fileInfo.isDirectory = () => isDirectory

    return fileInfo
}