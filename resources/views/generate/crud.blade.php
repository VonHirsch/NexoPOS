@inject( 'Schema', 'Illuminate\Support\Facades\Schema' )
@inject( 'Str', 'Illuminate\Support\Str' )
<?php
$model          =   explode( '\\', $model_name );
$lastClassName  =   $model[ count( $model ) - 1 ];
?>
<{{ '?php' }}
@if( isset( $module ) )
namespace Modules\{{ $module[ 'namespace' ] }}\Crud;
@else
namespace App\Crud;
@endif

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Services\CrudService;
use App\Services\CrudEntry;
use App\Exceptions\NotAllowedException;
use TorMorten\Eventy\Facades\Events as Hook;
use {{ trim( $model_name ) }};

class {{ ucwords( $Str::camel( $resource_name ) ) }}Crud extends CrudService
{
    /**
     * Defines if the crud class should be automatically discovered by NexoPOS.
     * If set to "true", you won't need to register that class on the "CrudServiceProvider".
     */
    const AUTOLOAD = true;

    /**
     * define the base table
     * @param string
     */
    protected $table = '{{ strtolower( trim( $table_name ) ) }}';

    /**
     * default slug
     * @param string
     */
    protected $slug = '{{ strtolower( trim( $route_name ) ) }}';

    /**
     * Define namespace
     * @param string
     */
    protected $namespace = '{{ strtolower( trim( $namespace ) ) }}';

    /**
     * To be able to autoload the class, we need to define
     * the identifier on a constant.
     */
    const IDENTIFIER = '{{ strtolower( trim( $namespace ) ) }}';

    /**
     * Model Used
     * @param string
     */
    protected $model = {{ trim( $lastClassName ) }}::class;

    /**
     * Define permissions
     * @param array
     */
    protected $permissions  =   [
        'create'    =>  true,
        'read'      =>  true,
        'update'    =>  true,
        'delete'    =>  true,
    ];

    /**
     * Adding relation
     * Example : [ 'nexopos_users as user', 'user.id', '=', 'nexopos_orders.author' ]
     * @param array
     */
    public $relations   =  [
        @if( isset( $relations ) && count( $relations ) > 0 )@foreach( $relations as $relation )[ '{{ strtolower( trim( $relation[0] ) ) }}', '{{ strtolower( trim( $relation[2] ) ) }}', '=', '{{ strtolower( trim( $relation[1] ) ) }}' ],
        @endforeach@endif
    ];

    /**
     * all tabs mentionned on the tabs relations
     * are ignored on the parent model.
     */
    protected $tabsRelations    =   [
        // 'tab_name'      =>      [ YourRelatedModel::class, 'localkey_on_relatedmodel', 'foreignkey_on_crud_model' ],
    ];

    /**
     * Export Columns defines the columns that
     * should be included on the exported csv file.
     */
    protected $exportColumns = []; // @getColumns will be used by default.

    /**
     * Pick
     * Restrict columns you retreive from relation.
     * Should be an array of associative keys, where 
     * keys are either the related table or alias name.
     * Example : [
     *      'user'  =>  [ 'username' ], // here the relation on the table nexopos_users is using "user" as an alias
     * ]
     */
    public $pick = [];

    /**
     * Define where statement
     * @var array
    **/
    protected $listWhere = [];

    /**
     * Define where in statement
     * @var array
     */
    protected $whereIn = [];

    /**
     * If few fields should only be filled
     * those should be listed here.
     */
    public $fillable = {!! json_encode( $fillable ?: [] ) !!};

    /**
     * If fields should be ignored during saving
     * those fields should be listed here
     */
    public $skippable = [];

    /**
     * Determine if the options column should display
     * before the crud columns
     */
    protected $prependOptions = false;

    /**
     * Will make the options column available per row if
     * set to "true". Otherwise it will be hidden.
     */
    protected $showOptions = true;

    /**
     * Here goes the CRUD constructor. Here you can change the behavior 
     * of the crud component.
     */
    public function __construct()
    {
        parent::__construct();

        Hook::addFilter( $this->namespace . '-crud-actions', [ $this, 'addActions' ], 10, 2 );
    }

    /**
     * Return the label used for the crud object.
    **/
    public function getLabels(): array
    {
        return [
            'list_title'            =>  __( '{{ ucwords( $Str::plural( trim( $resource_name ) ) ) }} List' ),
            'list_description'      =>  __( 'Display all {{ strtolower( $Str::plural( trim( $resource_name ) ) ) }}.' ),
            'no_entry'              =>  __( 'No {{ strtolower( $Str::plural( trim( $resource_name ) ) ) }} has been registered' ),
            'create_new'            =>  __( 'Add a new {{ strtolower( $Str::singular( trim( $resource_name ) ) ) }}' ),
            'create_title'          =>  __( 'Create a new {{ strtolower( $Str::singular( trim( $resource_name ) ) ) }}' ),
            'create_description'    =>  __( 'Register a new {{ strtolower( $Str::singular( trim( $resource_name ) ) ) }} and save it.' ),
            'edit_title'            =>  __( 'Edit {{ strtolower( $Str::singular( trim( $resource_name ) ) ) }}' ),
            'edit_description'      =>  __( 'Modify  {{ ucwords( strtolower( $Str::singular( trim( $resource_name ) ) ) ) }}.' ),
            'back_to_list'          =>  __( 'Return to {{ ucwords( $Str::plural( trim( $resource_name ) ) ) }}' ),
        ];
    }

    /**
     * Defines the forms used to create and update entries.
     */
    public function getForm( {{ trim( $lastClassName ) }} $entry = null ): array
    {
        return [
            'main' =>  [
                'label'         =>  __( 'Name' ),
                'name'          =>  'name',
                'value'         =>  $entry->name ?? '',
                'description'   =>  __( 'Provide a name to the resource.' )
            ],
            'tabs'  =>  [
                'general'   =>  [
                    'label'     =>  __( 'General' ),
                    'fields'    =>  [
                        @foreach( $Schema::getColumnListing( $table_name ) as $column )[
                            'type'  =>  'text',
                            'name'  =>  '{{ $column }}',
                            'label' =>  __( '{{ ucwords( $column ) }}' ),
                            'value' =>  $entry->{{ $column }} ?? '',
                        ], @endforeach
                    ]
                ]
            ]
        ];
    }

    /**
     * Filter POST input fields
     * @param array of fields
     * @return array of fields
     */
    public function filterPostInputs( $inputs ): array
    {
        return $inputs;
    }

    /**
     * Filter PUT input fields
     * @param array of fields
     * @return array of fields
     */
    public function filterPutInputs( array $inputs, {{ trim( $lastClassName ) }} $entry )
    {
        return $inputs;
    }

    /**
     * Trigger actions that are executed before the
     * crud entry is created.
     */
    public function beforePost( array $request ): array
    {
        if ( $this->permissions[ 'create' ] !== false ) {
            ns()->restrict( $this->permissions[ 'create' ] );
        } else {
            throw new NotAllowedException;
        }

        return $request;
    }

    /**
     * Trigger actions that will be executed 
     * after the entry has been created.
     */
    public function afterPost( array $request, {{ trim( $lastClassName ) }} $entry ): array
    {
        return $request;
    }

    
    /**
     * A shortcut and secure way to access
     * senstive value on a read only way.
     */
    public function get( string $param ): mixed
    {
        switch( $param ) {
            case 'model' : return $this->model ; break;
        }
    }

    /**
     * Trigger actions that are executed before
     * the crud entry is updated.
     */
    public function beforePut( array $request, {{ trim( $lastClassName ) }} $entry ): array
    {
        if ( $this->permissions[ 'update' ] !== false ) {
            ns()->restrict( $this->permissions[ 'update' ] );
        } else {
            throw new NotAllowedException;
        }

        return $request;
    }

    /**
     * This trigger actions that are executed after
     * the crud entry is successfully updated.
     */
    public function afterPut( array $request, {{ trim( $lastClassName ) }} $entry ): array
    {
        return $request;
    }

    /**
     * This triggers actions that will be executed ebfore
     * the crud entry is deleted.
     */
    public function beforeDelete( $namespace, $id, $model ): void
    {
        if ( $namespace == '{{ strtolower( trim( $namespace ) ) }}' ) {
            /**
             *  Perform an action before deleting an entry
             *  In case something wrong, this response can be returned
             *
             *  return response([
             *      'status'    =>  'danger',
             *      'message'   =>  __( 'You\re not allowed to do that.' )
             *  ], 403 );
            **/
            if ( $this->permissions[ 'delete' ] !== false ) {
                ns()->restrict( $this->permissions[ 'delete' ] );
            } else {
                throw new NotAllowedException;
            }
        }
    }

    /**
     * Define Columns and how it is structured.
     */
    public function getColumns(): array
    {
        return [
            @foreach( $Schema::getColumnListing( $table_name ) as $column )
'{{ $column }}'  =>  [
                'label'  =>  __( '{{ ucwords( $column ) }}' ),
                '$direction'    =>  '',
                '$sort'         =>  false
            ],
            @endforeach
        ];
    }

    /**
     * Define row actions.
     */
    public function addActions( CrudEntry $entry, $namespace ): CrudEntry
    {
        /**
         * Declaring entry actions
         */
        $entry->action( 
            identifier: 'edit',
            label: __( 'Edit' ),
            url: ns()->url( '/dashboard/' . $this->slug . '/edit/' . $entry->id )
        );
        
        $entry->action( 
            identifier: 'delete',
            label: __( 'Delete' ),
            type: 'DELETE',
            url: ns()->url( '/api/crud/{{ strtolower( trim( $namespace ) ) }}/' . $entry->id ),
            confirm: [
                'message'  =>  __( 'Would you like to delete this ?' ),
            ]
        );
        
        return $entry;
    }

    
    /**
     * trigger actions that are executed
     * when a bulk actio is posted.
     */
    public function bulkAction( Request $request ): array
    {
        /**
         * Deleting licence is only allowed for admin
         * and supervisor.
         */

        if ( $request->input( 'action' ) == 'delete_selected' ) {

            /**
             * Will control if the user has the permissoin to do that.
             */
            if ( $this->permissions[ 'delete' ] !== false ) {
                ns()->restrict( $this->permissions[ 'delete' ] );
            } else {
                throw new NotAllowedException;
            }

            $status     =   [
                'success'   =>  0,
                'failed'    =>  0
            ];

            foreach ( $request->input( 'entries' ) as $id ) {
                $entity     =   $this->model::find( $id );
                if ( $entity instanceof {{ trim( $lastClassName ) }} ) {
                    $entity->delete();
                    $status[ 'success' ]++;
                } else {
                    $status[ 'failed' ]++;
                }
            }
            return $status;
        }

        return Hook::filter( $this->namespace . '-catch-action', false, $request );
    }

    /**
     * Defines links used on the CRUD object.
     */
    public function getLinks(): array
    {
        return  [
            'list'      =>  ns()->url( 'dashboard/' . '{{ strtolower( trim( $route_name ) ) }}' ),
            'create'    =>  ns()->url( 'dashboard/' . '{{ strtolower( trim( $route_name ) ) }}/create' ),
            'edit'      =>  ns()->url( 'dashboard/' . '{{ strtolower( trim( $route_name ) ) }}/edit/' ),
            'post'      =>  ns()->url( 'api/crud/' . '{{ strtolower( trim( $namespace ) ) }}' ),
            'put'       =>  ns()->url( 'api/crud/' . '{{ strtolower( trim( $namespace ) ) }}/{id}' . '' ),
        ];
    }

    /**
     * Defines the bulk actions.
    **/
    public function getBulkActions(): array
    {
        return Hook::filter( $this->namespace . '-bulk', [
            [
                'label'         =>  __( 'Delete Selected Groups' ),
                'identifier'    =>  'delete_selected',
                'url'           =>  ns()->route( 'ns.api.crud-bulk-actions', [
                    'namespace' =>  $this->namespace
                ])
            ]
        ]);
    }

    /**
     * Defines the export configuration.
    **/
    public function getExports(): array
    {
        return [];
    }
}