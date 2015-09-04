
/*
    imageinfo-lib.js - a librarized version of imageinfo.js
    by WATANABE Hiroaki, <https://github.com/hiroaki/>.

    The original is "ImageInfo 0.1.2" - A JavaScript library for reading image metadata
    by Jacob Seidelin, http://blog.nihilogic.dk/

    Each are under the MIT License <http://www.nihilogic.dk/licenses/mit-license.txt>
*/

(function (){

    var ImageInfo = function (){
        this.initialize.apply(this,arguments);
    }
    ImageInfo.useRange = false;
    ImageInfo.range = 10240;
    ImageInfo.prototype = {
        initialize: function (url,cb){
            this.url = url;
            this.callback = cb;
            this.tags;
            this.response;
        },
        readFileData: function (){
            BinaryAjax(this.url,(function (that){
                return function (response){
                    that.response = response;
                    that.tags = that.readInfoFromData(that.response.binaryResponse);
                    that.callback(that);
                };
            })(this),null);
        },
        readInfoFromData: function (stream){
            var offset = 0;
            if(stream.getByteAt(0) == 0xFF && stream.getByteAt(1) == 0xD8){
                return this.readJPEGInfo(stream);
            }
            if(stream.getByteAt(0) == 0x89 && stream.getStringAt(1, 3) == 'PNG'){
                return this.readPNGInfo(stream);
            }
            if(stream.getStringAt(0,3) == 'GIF'){
                return this.readGIFInfo(stream);
            }
            if(stream.getByteAt(0) == 0x42 && stream.getByteAt(1) == 0x4D){
                return this.readBMPInfo(stream);
            }
            if(stream.getByteAt(0) == 0x00 && stream.getByteAt(1) == 0x00){
                return this.readICOInfo(stream);
            }
            return {};
        },
        readPNGInfo: function (stream){
            var w = stream.getLongAt(16,true);
            var h = stream.getLongAt(20,true);
            var bpc = stream.getByteAt(24);
            var ct = stream.getByteAt(25);
            var bpp = bpc;
            if (ct == 4) bpp *= 2;
            if (ct == 2) bpp *= 3;
            if (ct == 6) bpp *= 4;
            var alpha = stream.getByteAt(25) >= 4;
            return {
                format: 'PNG',
                version: '',
                width: w,
                height: h,
                bpp: bpp,
                alpha: alpha,
                exif: {}
            };
        },
        readGIFInfo: function (stream){
            var version = stream.getStringAt(3,3);
            var w = stream.getShortAt(6);
            var h = stream.getShortAt(8);
            var bpp = ((stream.getByteAt(10) >> 4) & 7) + 1;
            return {
                format: 'GIF',
                version: version,
                width: w,
                height: h,
                bpp: bpp,
                alpha: false,
                exif: {}
            };
        },
        readJPEGInfo_original: function (stream){
            var w = 0;
            var h = 0;
            var comps = 0;
            var len = stream.getLength();
            var offset = 2;
            while (offset < len) {
                var marker = stream.getShortAt(offset, true);
                offset += 2;
                if (marker == 0xFFC0) {
                    h = stream.getShortAt(offset + 3, true);
                    w = stream.getShortAt(offset + 5, true);
                    comps = stream.getByteAt(offset + 7, true)
                    break;
                } else {
                    offset += stream.getShortAt(offset, true)
                }
            }
    
            var exif = {};
            if (typeof EXIF != 'undefined' && EXIF.readFromBinaryFile) {
                exif = EXIF.readFromBinaryFile(stream);
            }
    
            return {
                format: 'JPEG',
                version: '',
                width: w,
                height: h,
                bpp: comps * 8,
                alpha: false,
                exif: exif
            };
        },
        readJPEGInfo: function (stream){
            // this algorithm reading jpeg was borrowed from
            // http://cpansearch.perl.org/src/RJRAY/Image-Size-2.992/Size.pm

            var SIZE_FIRST = 0xc0;
            var SIZE_LAST  = 0xc3;
            var x,y,comps;
            var id = 'could not determine JPEG size';
            var size = stream.getLength();
    
            var ptr = 1;
            stream.getByteAt(ptr);
            while( ptr < size ){
                var marker = stream.getByteAt(ptr+1);
                var code   = stream.getByteAt(ptr+2);
                var len    = stream.getShortAt(ptr+3,true);
                ptr += 4;
                if( marker != 0xff ){
                    throw 'JPEG marker not found';
                    break;
                }else if( code >= SIZE_FIRST && code <= SIZE_LAST ){
                    len = 5;
                    stream.getByteAt(ptr+1);
                    y = stream.getShortAt(ptr+2,true);
                    x = stream.getShortAt(ptr+4,true);
                    comps = stream.getByteAt(ptr+6);
                    id = 'JPEG';
                    break;
                }else{
                    ptr += len -2;
                }
            }
    
            var exif = {};
            if (typeof EXIF != 'undefined' && EXIF.readFromBinaryFile) {
                exif = EXIF.readFromBinaryFile(stream);
            }

            return {
                "format": id,
                "version": '',
                "width": x,
                "height": y,
                "bpp": comps * 8,
                "alpha": false,
                "exif": exif
            };
        },
        readBMPInfo: function (stream){
            var w = stream.getLongAt(18);
            var h = stream.getLongAt(22);
            var bpp = stream.getShortAt(28);
            return {
                format: 'BMP',
                version: '',
                width: w,
                height: h,
                bpp: bpp,
                alpha: false,
                exif: {}
            };
        },
        getAllFields: function (){
            return this.tags;
        },
        getField: function (name){
            return this.tags[name];
        }
    }
    
    window.ImageInfo = ImageInfo;
    
})();
