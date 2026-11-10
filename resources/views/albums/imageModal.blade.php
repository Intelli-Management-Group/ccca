<div class="modal fade" id="imageUploadModal" tabindex="-1" role="dialog"
     aria-labelledby="imageUploadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Upload images</h5>
                <button type="button" class="close" data-dismiss="modal"
                        aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="albumImages" class="dropzone"></div>
                <div id="imagePreview" class="dropzone-previews"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    Close
                </button>
                <button type="button" class="btn btn-primary" onclick="javascript:saveImageData()">Save changes
                </button>
            </div>
        </div>
    </div>
    <div class="table table-striped" class="files" id="previews">
        <div id="template" class="dz-preview">
            <div>
                <span class="preview"><img data-dz-thumbnail/></span>
            </div>
            <div>
                <strong class="error text-danger" data-dz-errormessage></strong>
            </div>
            <div class="mt-2">
                <input type="text" placeholder="Caption" class="form-control image-caption"/>
            </div>
            <div class="mt-2">
                <input type="text" placeholder="Credit" class="form-control image-credit"/>
            </div>
            <a class="d-block text-right mt-2"><i class="fas fa-trash-alt data-dz-remove text-danger cursor_pointer"
                                                  data-dz-remove></i></a>
        </div>
    </div>
</div>
<script src="{{ asset('js/dropzone.js') }}"></script>
<link href="{{ asset('css/basic.css') }}" rel="stylesheet">
<link href="{{ asset('css/dropzone.css') }}" rel="stylesheet">
<script src="{{ asset('js/Sortable.js') }}"></script>
<script type="text/javascript">
    var files_Data = [], deleted_ids = [], sort_ids = {};
    var cover_image = "";
    $(document).ready(function () {
        var existing_images = {!! json_encode(isset($images) ? $images->toJson() : "", JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
        $("#cover_image,#imgupload").change(function () {
            cover_image = this.files[0];
        });
        Dropzone.autoDiscover = false;
        var previewNode = document.querySelector("#template");
        previewNode.removeAttribute("id");
        var previewTemplate = previewNode.parentNode.innerHTML;
        previewNode.parentNode.removeChild(previewNode);

        $("div#albumImages").dropzone({
            url: "null",
            autoProcessQueue: false,
            acceptedFiles: "image/*",
            previewTemplate: previewTemplate,
            thumbnailWidth: 187,
            thumbnailHeight: 120,
            init: function () {
                if (existing_images != "") {
                    let myDropzone = this;
                    existing_images = $.parseJSON(existing_images);
                    for (var i in existing_images) {
                        var mockFile = {
                            name: existing_images[i].fileName,
                            size: "",
                            dataId: existing_images[i].id,
                            dataURL: '{!! asset("uploads/albums") !!}' + "/" + existing_images[i].albumId + "/" + existing_images[i].fileName
                        };
                        myDropzone.emit("addedfile", mockFile);
                        createThumb(myDropzone, mockFile)
                        myDropzone.emit('complete', mockFile);
                        $(mockFile.previewElement).attr("data-id", existing_images[i].id)
                        $(mockFile.previewElement).find(".image-caption").val(existing_images[i].caption);
                        $(mockFile.previewElement).find(".image-credit").val(existing_images[i].credit);
                    }
                }
                this.on("addedfile", function (file) {
                    var uuid = file.upload.uuid;
                    files_Data[uuid] = {
                        sortIndex: $(file.previewElement).index(),
                        file: file,
                        caption: "",
                        credit: ""
                    };
                    file.previewElement.setAttribute("id", uuid);
                });
                this.on("removedfile", function (file) {
                    if (file.upload != undefined) {
                        delete files_Data[file.upload.uuid];
                    } else {
                        deleted_ids.push(file.dataId);
                    }
                });
            }
        });
        Sortable.create(albumImages);

    });

    function createThumb(myDropzone, mockFile) {
        myDropzone.createThumbnailFromUrl(mockFile,
            myDropzone.options.thumbnailWidth,
            myDropzone.options.thumbnailHeight,
            myDropzone.options.thumbnailMethod, true, function (thumbnail) {
                myDropzone.emit('thumbnail', mockFile, thumbnail);
            });
    }
</script>
