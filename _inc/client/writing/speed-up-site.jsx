/**
 * External dependencies
 */
import React, { Component } from 'react';
import { connect } from 'react-redux';
import { translate as __ } from 'i18n-calypso';

/**
 * Internal dependencies
 */
import { FormFieldset } from 'components/forms';
import CompactFormToggle from 'components/form/form-toggle/compact';
import { ModuleSettingsForm as moduleSettingsForm } from 'components/module-settings/module-settings-form';
import { getModule } from 'state/modules';
import { isModuleFound as _isModuleFound } from 'state/search';
import SettingsCard from 'components/settings-card';
import SettingsGroup from 'components/settings-group';
import { ModuleToggle } from 'components/module-toggle';

const SpeedUpSite = moduleSettingsForm(
	class extends Component {
		toggleModule = ( name, value ) => {
			if ( 'photon' === name ) {
				// Tiled Galleries depends on Photon. Deactivate it if Photon is deactivated.
				if ( false === ! value ) {
					this.props.updateOptions( { photon: false, 'tiled-gallery': false, tiled_galleries: false } );
				} else {
					this.props.updateOptions( { photon: true, 'tiled-gallery': true, tiled_galleries: true } );
				}
			} else {
				this.props.updateOptions( { [ name ]: ! value } );
			}
		};

		handleCdnChange = () => {
			const currentPhoton = this.props.getOptionValue( 'photon' );
			const currentCdn = this.props.getOptionValue( 'photon-cdn' );

			// Tiled Galleries depends on Photon. Deactivate it when Photon is deactivated.
			this.props.updateOptions( {
				photon: ! currentPhoton,
				'tiled-gallery': ! currentPhoton,
				tiled_galleries: ! currentPhoton,
				'photon-cdn': ! currentCdn
			} );
		};

		render() {
			const foundPhoton = this.props.isModuleFound( 'photon' );
			const foundPhotonCdn = this.props.isModuleFound( 'photon-cdn' );
			const foundLazyImages = this.props.isModuleFound( 'lazy-images' );

			if ( ! foundPhoton && ! foundLazyImages && ! foundPhotonCdn ) {
				return null;
			}

			const photon = this.props.module( 'photon' );
			const lazyImages = this.props.module( 'lazy-images' );

			// Check if any of the CDN options are on.
			const CdnStatus = this.props.getOptionValue( 'photon' ) || this.props.getOptionValue( 'photon-cdn' );

			return (
				<SettingsCard
					{ ...this.props }
					header={ __( 'Performance & speed' ) }
					hideButton>

					{ foundPhoton && foundPhotonCdn &&
						<SettingsGroup
							hasChild
							module={ photon }
							support={ {
								link: 'https://jetpack.com/support/image-cdn/',
							} }
							>
							<p>
								{ __(
									"Jetpack's global Content Delivery Network (CDN) optimizes " +
										'files and images so your visitors enjoy ' +
										'the fastest experience regardless of device or location.'
								) }
							</p>
							<CompactFormToggle
								checked={ CdnStatus }
								toggling={ this.props.isSavingAnyOption( [ 'photon', 'photon-cdn' ] ) && ! CdnStatus }
								onChange={ this.handleCdnChange }
							>
								<span className="jp-form-toggle-explanation">
									{ __( 'Enable Site Accelerator' ) }
								</span>
							</CompactFormToggle>
							<FormFieldset>
								<ModuleToggle
									slug="photon"
									disabled={ this.props.isUnavailableInDevMode( 'photon' ) }
									activated={ this.props.getOptionValue( 'photon' ) }
									toggling={ this.props.isSavingAnyOption( 'photon' ) }
									toggleModule={ this.toggleModule }
								>
									<span className="jp-form-toggle-explanation">
										{ __( 'Speed up images' ) }
									</span>
								</ModuleToggle>
								<ModuleToggle
									slug="photon-cdn"
									activated={ this.props.getOptionValue( 'photon-cdn' ) }
									toggling={ this.props.isSavingAnyOption( 'photon-cdn' ) }
									toggleModule={ this.toggleModule }
								>
									<span className="jp-form-toggle-explanation">
										{ __( 'Speed up all static files (CSS and JavaScript) for WordPress, WooCommerce, and Jetpack' ) }
									</span>
								</ModuleToggle>
							</FormFieldset>
						</SettingsGroup>
					}

					{ foundLazyImages &&
						<SettingsGroup
							hasChild
							module={ lazyImages }
							support={ {
								link: 'https://jetpack.com/support/lazy-images/',
							} }
							>
							<p>
								{ __(
									"Lazy-loading images improve your site's speed and create a " +
										'smoother viewing experience. Images will load as visitors ' +
										'scroll down the screen, instead of all at once.'
								) }
							</p>
							<ModuleToggle
								slug="lazy-images"
								disabled={ this.props.isUnavailableInDevMode( 'lazy-images' ) }
								activated={ this.props.getOptionValue( 'lazy-images' ) }
								toggling={ this.props.isSavingAnyOption( 'lazy-images' ) }
								toggleModule={ this.toggleModule }
							>
								<span className="jp-form-toggle-explanation">
									{ __( 'Enable Lazy Loading for images' ) }
								</span>
							</ModuleToggle>
						</SettingsGroup>
					}
				</SettingsCard>
			);
		}
	}
);

export default connect(
	( state ) => {
		return {
			module: ( module_name ) => getModule( state, module_name ),
			isModuleFound: ( module_name ) => _isModuleFound( state, module_name )
		};
	}
)( SpeedUpSite );
